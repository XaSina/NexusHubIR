<?php
/**
 * مشاهده و ویرایش اطلاعات یک عضو سایت
 */
require __DIR__ . '/inc/boot.php';
require_cap('members');

$id = (int) ($_GET['id'] ?? 0);
$m = row('SELECT * FROM members WHERE id = ?', [$id]);
if (!$m) { flash('عضو یافت نشد.', 'error'); redirect('admin/members.php'); }
$errors = [];

if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? 'save';
    if ($act === 'delete') {
        q('DELETE FROM members WHERE id = ?', [$id]);
        flash('عضو حذف شد.');
        redirect('admin/members.php');
    }
    [$d, $errors] = validate_member_input($_POST, $id, false);
    $pw = (string) ($_POST['new_password'] ?? '');
    if ($pw !== '' && strlen($pw) < 8) $errors['new_password'] = 'رمز جدید باید حداقل ۸ کاراکتر باشد.';
    if (!$errors) {
        $d['status'] = ($_POST['status'] ?? '') === 'banned' ? 'banned' : 'active';
        $d['note'] = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        $d['updated_at'] = now();
        if ($pw !== '') $d['password'] = password_hash($pw, PASSWORD_DEFAULT);
        update('members', $d, $id);
        flash('اطلاعات عضو ذخیره شد ✅' . ($pw !== '' ? ' (رمز عبور تغییر کرد)' : ''));
        redirect('admin/member-view.php?id=' . $id);
    }
    $m = array_merge($m, array_intersect_key($_POST, array_flip(['first_name', 'last_name', 'phone', 'email', 'username', 'status', 'note'])));
}
$commentsCount = (int) val('SELECT COUNT(*) FROM comments WHERE email = ?', [$m['email']]);
$ferr = fn($k) => !empty($errors[$k]) ? '<small class="af-hint" style="color:var(--err)">' . e($errors[$k]) . '</small>' : '';

adm_head('عضو: ' . member_full_name($m), 'members');
?>
<div class="split">
    <form class="card" method="post">
        <?= csrf_field() ?><input type="hidden" name="act" value="save">
        <div class="card-head"><h3><?= adm_icon('edit', 20) ?> ویرایش اطلاعات عضو</h3><a class="abtn abtn-ghost abtn-sm" href="members.php">← بازگشت به لیست</a></div>
        <?php if ($errors): ?><div class="alert alert-warn"><?= e(reset($errors)) ?></div><?php endif; ?>
        <div class="grid g2">
            <div><?= f_text('first_name', 'نام', $m['first_name'], ['required' => true]) ?><?= $ferr('first_name') ?></div>
            <div><?= f_text('last_name', 'نام خانوادگی', $m['last_name'], ['required' => true]) ?><?= $ferr('last_name') ?></div>
            <div><?= f_text('phone', 'شماره موبایل', $m['phone'], ['ltr' => true, 'required' => true]) ?><?= $ferr('phone') ?></div>
            <div><?= f_text('email', 'ایمیل', $m['email'], ['type' => 'email', 'ltr' => true, 'required' => true]) ?><?= $ferr('email') ?></div>
            <div><?= f_text('username', 'نام کاربری', $m['username'], ['ltr' => true, 'required' => true]) ?><?= $ferr('username') ?></div>
            <div><?= f_select('status', 'وضعیت حساب', ['active' => 'فعال', 'banned' => 'مسدود'], $m['status']) ?></div>
        </div>
        <?= f_text('new_password', 'تعیین رمز جدید (خالی = بدون تغییر)', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="new-password"']) ?><?= $ferr('new_password') ?>
        <?= f_textarea('note', 'یادداشت مدیر (فقط برای شما قابل مشاهده است)', (string) $m['note'], ['rows' => 3]) ?>
        <div class="form-actions">
            <button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره تغییرات</button>
        </div>
    </form>

    <div class="split-side">
        <div class="card" style="text-align:center">
            <span class="avatar avatar-letter" style="width:84px;height:84px;font-size:2rem;margin:4px auto 10px;box-shadow:0 0 30px rgba(var(--primary-rgb),.6)"><?= e(mb_substr($m['first_name'], 0, 1)) ?></span>
            <h3 style="margin:0"><?= e(member_full_name($m)) ?></h3>
            <p class="muted ltr" style="margin:2px 0 12px">@<?= e($m['username']) ?></p>
            <span class="badge b-hot ltr" style="font-size:.95rem;padding:6px 16px;cursor:pointer" data-copy="<?= e($m['member_code']) ?>" title="کپی کد"><?= e($m['member_code']) ?></span>
            <div class="mt"><?= $m['status'] === 'active' ? '<span class="badge b-ok">حساب فعال</span>' : '<span class="badge b-err">حساب مسدود</span>' ?></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('chart', 20) ?> فعالیت</h3></div>
            <div class="list-item"><div class="li-body"><b>تاریخ عضویت</b><p><?= jdate('l j F Y — H:i', $m['created_at']) ?></p></div></div>
            <div class="list-item"><div class="li-body"><b>آخرین ورود</b><p><?= $m['last_login'] ? jdate('l j F Y — H:i', $m['last_login']) . ' (' . jago($m['last_login']) . ')' : 'هنوز وارد نشده' ?></p></div></div>
            <div class="list-item"><div class="li-body"><b>تعداد ورود</b></div><span class="badge"><?= fa_num((string) $m['login_count']) ?></span></div>
            <div class="list-item"><div class="li-body"><b>روزهای عضویت</b></div><span class="badge"><?= fa_num((string) member_days($m)) ?></span></div>
            <div class="list-item"><div class="li-body"><b>نظرات ثبت‌شده</b></div><a class="badge b-info" href="comments.php?status=all"><?= fa_num((string) $commentsCount) ?></a></div>
            <div class="list-item"><div class="li-body"><b>آخرین IP</b></div><span class="ltr small muted"><?= e($m['last_ip'] ?: '—') ?></span></div>
            <?php if ($m['updated_at']): ?><div class="list-item"><div class="li-body"><b>آخرین ویرایش</b><p><?= jago($m['updated_at']) ?></p></div></div><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>⚡ تماس سریع</h3></div>
            <div class="form-actions">
                <a class="abtn abtn-ghost abtn-sm" href="tel:<?= e($m['phone']) ?>"><?= icon('whatsapp', 15) ?> تماس</a>
                <a class="abtn abtn-ghost abtn-sm" href="mailto:<?= e($m['email']) ?>"><?= icon('mail', 15) ?> ایمیل</a>
                <button class="abtn abtn-ghost abtn-sm" type="button" data-copy="<?= e($m['phone']) ?>"><?= icon('copy', 15) ?> کپی شماره</button>
            </div>
            <form method="post" class="mt"><?= csrf_field() ?><button class="abtn abtn-danger abtn-sm abtn-block" name="act" value="delete" data-confirm="این عضو برای همیشه حذف شود؟"><?= adm_icon('trash', 15) ?> حذف عضو</button></form>
        </div>
    </div>
</div>
<?php adm_foot();
