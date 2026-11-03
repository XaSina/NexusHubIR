<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'download') {
        @set_time_limit(300);
        $tables = array_map(fn($r) => array_values($r)[0], rows('SHOW TABLES'));
        $name = 'nexushub-backup-' . jdate('Y-m-d', null, false) . '-' . date('His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo "-- NEXUS | HUB backup\n-- " . jdate('Y/m/d H:i', null, false) . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        $pdo = db();
        foreach ($tables as $t) {
            $create = row('SHOW CREATE TABLE `' . str_replace('`', '', $t) . '`');
            echo "DROP TABLE IF EXISTS `$t`;\n" . array_values($create)[1] . ";\n\n";
            $st = $pdo->query("SELECT * FROM `$t`");
            $batch = [];
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                if (count($batch) >= 100) { echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; }
            }
            if ($batch) echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
            echo "\n";
        }
        echo "SET FOREIGN_KEY_CHECKS=1;\n";
        exit;
    }
    if ($act === 'clear_stats') { q('DELETE FROM stats_daily'); flash('آمار بازدید روزانه پاک شد.'); }
    if ($act === 'reset_all_stats') {
        // start real stats from zero: visits chart, per-post views, ad impressions/clicks (content is kept)
        q('DELETE FROM stats_daily');
        q('UPDATE posts SET views = 0');
        q('UPDATE ads SET impressions = 0, clicks = 0');
        flash('همه آمارها صفر شد ✅ از این لحظه فقط بازدیدهای واقعی شمرده می‌شوند.');
    }
    if ($act === 'clear_reactions_log') { q('DELETE FROM reaction_log WHERE created_at < ?', [date('Y-m-d H:i:s', strtotime('-90 days'))]); flash('لاگ واکنش‌های قدیمی پاک شد.'); }
    if ($act === 'clear_unused_tags') { q('DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM post_tags)'); flash('برچسب‌های بدون استفاده حذف شدند.'); }
    redirect('admin/backup.php');
}
$dbSize = (float) val('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
$upSize = (int) val('SELECT COALESCE(SUM(size),0) FROM media');
adm_head('پشتیبان‌گیری و ابزارها', 'backup');
?>
<div class="grid g2">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('database', 20) ?> پشتیبان پایگاه داده</h3></div>
        <p class="muted">حجم فعلی دیتابیس: <b><?= fa_num(number_format($dbSize / 1048576, 2)) ?></b> مگابایت · حجم تصاویر: <b><?= fa_num(number_format($upSize / 1048576, 1)) ?></b> مگابایت</p>
        <form method="post"><?= csrf_field() ?><button class="abtn abtn-primary" name="act" value="download"><?= adm_icon('upload', 18) ?> دانلود فایل پشتیبان (SQL)</button></form>
        <div class="alert alert-info small mt">
            <b>انتقال از XAMPP به هاست cPanel:</b><br>
            ۱. از اینجا فایل SQL را دانلود کنید.<br>
            ۲. در cPanel یک دیتابیس و کاربر بسازید و از phpMyAdmin فایل SQL را Import کنید.<br>
            ۳. همه فایل‌ها (به همراه پوشه uploads) را آپلود کنید و اطلاعات دیتابیس را در <span class="ltr">app/config.php</span> ویرایش کنید.
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>🚀 شروع آمار واقعی</h3></div>
        <p class="muted small">اگر سایت را با «محتوای نمونه» نصب کرده‌اید، آمارهای داشبورد ساختگی هستند. با این دکمه همه‌ی آمارها صفر می‌شوند و از این لحظه فقط بازدیدهای واقعی شمرده می‌شود.</p>
        <ul class="muted small" style="margin:0 0 14px;padding-right:18px;line-height:2">
            <li>صفر می‌شود: نمودار و آمار بازدید روزانه، تعداد بازدید هر خبر، نمایش و کلیک تبلیغات</li>
            <li>دست نمی‌خورد: اخبار، دسته‌ها، تبلیغات، اعضا، نظرات و تنظیمات</li>
            <li>بازدیدهای خود شما (وقتی وارد پنل هستید) هیچ‌وقت شمرده نمی‌شود</li>
        </ul>
        <form method="post"><?= csrf_field() ?><button class="abtn abtn-primary" name="act" value="reset_all_stats" data-confirm="همه آمارها (بازدیدها، بازدید اخبار، نمایش و کلیک تبلیغات) صفر شود؟ این کار قابل بازگشت نیست."><?= icon('check', 17) ?> شروع آمار از صفر</button></form>
    </div>
    <div class="card">
        <div class="card-head"><h3>🧹 ابزارهای پاکسازی</h3></div>
        <form method="post" class="form-actions" style="flex-direction:column;align-items:stretch">
            <?= csrf_field() ?>
            <button class="abtn abtn-ghost" name="act" value="clear_unused_tags">حذف برچسب‌های بدون استفاده</button>
            <button class="abtn abtn-ghost" name="act" value="clear_reactions_log">پاکسازی لاگ واکنش‌های قدیمی‌تر از ۹۰ روز</button>
            <button class="abtn abtn-danger" name="act" value="clear_stats" data-confirm="همه آمار بازدید روزانه پاک شود؟">صفر کردن آمار بازدید</button>
        </form>
        <div class="mt small muted">نسخه PHP: <span class="ltr"><?= PHP_VERSION ?></span> · نسخه سیستم: <?= fa_num(NX_VERSION) ?> · حداکثر آپلود: <span class="ltr"><?= e(ini_get('upload_max_filesize')) ?></span></div>
    </div>
</div>
<?php adm_foot();
