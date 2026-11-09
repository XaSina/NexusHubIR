<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');
require_once NX_APP . '/projectdoc.php';

if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if (in_array($act, ['download', 'project', 'readme', 'xampp'], true) && !adm_pw_check()) redirect('admin/backup.php');
    if ($act === 'download') {
        $name = 'nexushub-backup-' . jdate('Y-m-d', null, false) . '-' . date('His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        sql_dump_to($out);
        exit;
    }
    if ($act === 'xampp') {
        try {
            $zip = project_xampp_zip(!empty($_POST['xx_uploads']));
        } catch (Throwable $e) {
            flash('ساخت فایل XAMPP ممکن نشد: ' . $e->getMessage(), 'error');
            redirect('admin/backup.php');
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="nexushub-xampp-' . jdate('Y-m-d', null, false) . '.zip"');
        header('Content-Length: ' . filesize($zip));
        readfile($zip);
        @unlink($zip);
        exit;
    }
    if ($act === 'project' || $act === 'readme') {
        $o = ['db' => !empty($_POST['x_db']), 'uploads' => !empty($_POST['x_uploads']), 'safe' => !empty($_POST['x_safe'])];
        save_settings(['export_db' => $o['db'] ? '1' : '0', 'export_uploads' => $o['uploads'] ? '1' : '0', 'export_safe' => $o['safe'] ? '1' : '0']);
        if ($act === 'readme') {
            header('Content-Type: text/markdown; charset=utf-8');
            header('Content-Disposition: attachment; filename="README.md"');
            echo project_readme($o);
            exit;
        }
        try {
            $zip = project_export_zip($o);
        } catch (Throwable $e) {
            flash('ساخت فایل ZIP ممکن نشد: ' . $e->getMessage(), 'error');
            redirect('admin/backup.php');
        }
        $name = 'nexushub-project-' . jdate('Y-m-d', null, false) . ($o['safe'] ? '-safe' : '-full') . '.zip';
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($zip));
        readfile($zip);
        @unlink($zip);
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
$exDb = setting_on('export_db'); $exSafe = setting_on('export_safe'); $exUp = setting_on('export_uploads');
adm_head('پشتیبان‌گیری و ابزارها', 'backup');
?>
<form method="post" class="card pex" data-pw>
    <?= csrf_field() ?>
    <div class="pex-head">
        <span class="pex-ic">📦</span>
        <div>
            <h3>دانلود کل پروژه در یک فایل ZIP</h3>
            <p class="muted small">همه‌ی فایل‌های سایت + فایل <b class="ltr">README.md</b> که توضیح می‌ده سایت چطور ساخته شده، کجاها رو می‌شه تغییر داد و به چی نباید دست زد؛ برای انتقال سایت یا دادن به یه برنامه‌نویس یا هوش مصنوعی.</p>
        </div>
    </div>
    <div class="pex-grid">
        <div class="pex-readme">
            <div class="pex-file"><span>📄</span><b class="ltr">README.md</b><small>خودکار، همیشه داخل ZIP</small></div>
            <ul>
                <li><i>#</i> How it was built <small>— نسخه، تاریخ، محتوای ZIP</small></li>
                <li><i>##</i> Built with <small>— فناوری‌ها</small></li>
                <li><i>##</i> Folder map &amp; database <small>— نقشه‌ی فایل‌ها و جدول‌ها</small></li>
                <li><i>##</i> Instructions <small>— نصب و قوانین کدنویسی</small></li>
                <li class="ok"><i>##</i> To change it ✅ <small>— جاهای امن برای تغییر</small></li>
                <li class="no"><i>##</i> Never touch ❌ <small>— چیزهایی که نباید دست بخوره</small></li>
                <li><i>##</i> Prompt <small>— متن آماده برای هوش مصنوعی</small></li>
            </ul>
        </div>
        <div class="pex-opts">
            <?= f_switch('x_safe', '🛡️ نسخه‌ی امن برای اشتراک‌گذاری', $exSafe, 'بدون رمزها، توکن ربات، لینک مخفی پنل، config.php و اطلاعات شخصی اعضا و سفارش‌ها — برای دادن به هوش مصنوعی یا برنامه‌نویس همین رو روشن بذار') ?>
            <?= f_switch('x_db', '🗄️ همراه دیتابیس (SQL)', $exDb, 'ساختار همه‌ی جدول‌ها + محتوا (در نسخه‌ی امن، داده‌های شخصی حذف می‌شن)') ?>
            <?= f_switch('x_uploads', '🖼️ همراه عکس‌ها و فایل‌های آپلودی', $exUp, 'حجم فعلی: ' . fa_num(number_format($upSize / 1048576, 1)) . ' مگابایت — اگه فقط برای هوش مصنوعی می‌خوای خاموش بمونه سبک‌تره') ?>
            <div class="pex-btns">
                <button class="abtn abtn-primary" name="act" value="project" data-download>⬇️ دانلود ZIP کامل پروژه</button>
                <button class="abtn abtn-ghost" name="act" value="readme" data-download>📄 فقط README.md</button>
            </div>
            <p class="pex-warn small" data-safe-off<?= $exSafe ? ' hidden' : '' ?>>⚠️ نسخه‌ی کامل شامل رمز دیتابیس و اطلاعات اعضاست؛ فقط برای پشتیبان شخصی خودت نگهش دار و به کسی نده.</p>
        </div>
    </div>
</form>
<form method="post" class="card pex pex-xampp" data-pw>
    <?= csrf_field() ?>
    <div class="pex-head">
        <span class="pex-ic">💻</span>
        <div>
            <h3>نسخه‌ی آماده برای XAMPP (اجرا روی کامپیوتر خودت)</h3>
            <p class="muted small">کل سایت با همه‌ی اخبار، اعضا، سفارش‌ها و تنظیمات؛ فایل <span class="ltr">config.php</span> از قبل برای XAMPP تنظیم شده و فایل دیتابیس خودش دیتابیس رو می‌سازه. راهنمای فارسی قدم‌به‌قدم هم داخل ZIP هست.</p>
        </div>
    </div>
    <ol class="pex-steps">
        <li>پوشه‌ی <b class="ltr">nexushub</b> رو بذار توی <b class="ltr">C:\xampp\htdocs</b></li>
        <li>توی <b class="ltr">localhost/phpmyadmin</b> فایل <b class="ltr">nexushub-database.sql</b> رو Import کن</li>
        <li>برو به <b class="ltr">localhost/nexushub</b> — تمام 🎉</li>
    </ol>
    <div class="pex-opts">
        <?= f_switch('xx_uploads', '🖼️ همراه عکس‌ها و فایل‌های آپلودی', true, 'حجم فعلی: ' . fa_num(number_format($upSize / 1048576, 1)) . ' مگابایت — بدون عکس‌ها، تصویر خبرها روی کامپیوتر خالی دیده می‌شن') ?>
        <div class="pex-btns"><button class="abtn abtn-primary" name="act" value="xampp" data-download>⬇️ دانلود نسخه‌ی XAMPP</button></div>
        <p class="pex-warn small">⚠️ این نسخه کامل و خصوصیه (اطلاعات اعضا داخلشه)؛ برای خودت نگهش دار.</p>
    </div>
</form>
<script>
document.addEventListener('change', function (e) {
    if (e.target.name !== 'x_safe' || e.target.type !== 'checkbox') return;
    var w = document.querySelector('[data-safe-off]'); if (w) w.hidden = e.target.checked;
});
</script>
<div class="grid g2">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('database', 20) ?> پشتیبان پایگاه داده</h3></div>
        <p class="muted">حجم فعلی دیتابیس: <b><?= fa_num(number_format($dbSize / 1048576, 2)) ?></b> مگابایت · حجم تصاویر: <b><?= fa_num(number_format($upSize / 1048576, 1)) ?></b> مگابایت</p>
        <form method="post" data-pw><?= csrf_field() ?><button class="abtn abtn-primary" name="act" value="download" data-download><?= adm_icon('upload', 18) ?> دانلود فایل پشتیبان (SQL)</button></form>
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
