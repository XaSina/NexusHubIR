<?php
require __DIR__ . '/inc/boot.php';
$me = current_user();
if (is_post()) {
    csrf_check();
    $data = [
        'display_name' => mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 120),
        'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190),
        'avatar' => trim((string) ($_POST['avatar'] ?? '')) ?: null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500),
    ];
    $new = (string) ($_POST['new_password'] ?? '');
    if ($new !== '') {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $me['password'])) { flash('رمز فعلی اشتباه است.', 'error'); redirect('admin/profile.php'); }
        if (strlen($new) < 8) { flash('رمز جدید باید حداقل ۸ کاراکتر باشد.', 'error'); redirect('admin/profile.php'); }
        if ($new !== (string) ($_POST['confirm_password'] ?? '')) { flash('تکرار رمز جدید مطابقت ندارد.', 'error'); redirect('admin/profile.php'); }
        $data['password'] = password_hash($new, PASSWORD_DEFAULT);
    }
    update('users', $data, (int) $me['id']);
    if (isset($data['password'])) $_SESSION['upw'] = substr(hash('sha256', $data['password']), 0, 32);
    flash('پروفایل بروزرسانی شد.');
    redirect('admin/profile.php');
}
adm_head('پروفایل من', '');
?>
<form method="post" class="grid g2">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-head"><h3>👤 اطلاعات من</h3><span class="badge b-hot"><?= e(role_names()[$me['role']] ?? '') ?></span></div>
        <?= f_text('username', 'نام کاربری', $me['username'], ['ltr' => true, 'attrs' => 'disabled']) ?>
        <?= f_text('display_name', 'نام نمایشی', $me['display_name']) ?>
        <?= f_text('email', 'ایمیل', $me['email'], ['type' => 'email', 'ltr' => true]) ?>
        <?= f_textarea('bio', 'بیوگرافی', (string) $me['bio'], ['rows' => 3]) ?>
    </div>
    <div class="card">
        <div class="logo-pick"><?= f_image('avatar', 'تصویر پروفایل', $me['avatar']) ?></div>
        <div class="card-head mt"><h3>🔐 تغییر رمز عبور</h3></div>
        <?= f_text('current_password', 'رمز فعلی', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="current-password"']) ?>
        <?= f_text('new_password', 'رمز جدید', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="new-password"']) ?>
        <?= f_text('confirm_password', 'تکرار رمز جدید', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="new-password"']) ?>
        <button class="abtn abtn-primary abtn-block" type="submit">ذخیره</button>
    </div>
</form>
<?php adm_foot();
