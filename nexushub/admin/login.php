<?php
define('NX_ADMIN_PUBLIC', true);
require __DIR__ . '/inc/boot.php';

if (current_user()) redirect('admin/');
$error = '';
$wait = login_blocked();
if (is_post()) {
    if (!csrf_valid()) $error = 'نشست منقضی شده، دوباره تلاش کنید.';
    elseif ($wait > 0) $error = 'به دلیل تلاش‌های ناموفق، ' . fa_num((string) ceil($wait / 60)) . ' دقیقه دیگر تلاش کنید.';
    elseif (attempt_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        redirect('admin/');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ورود به پنل مدیریت | <?= e(setting('site_title')) ?></title>
<link rel="icon" href="<?= e(media_url((string) setting('favicon'))) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style><?= theme_css_vars() ?></style>
</head>
<body class="adm-body">
<div class="adm-bg" aria-hidden="true"><i></i><i></i></div>
<div class="login-wrap">
    <div class="login-box glass">
        <div class="login-logo"><img src="<?= e(media_url((string) setting('logo'))) ?>" alt=""></div>
        <h1><?= e(setting('site_title')) ?></h1>
        <p>ورود به پنل مدیریت</p>
        <form method="post">
            <?= csrf_field() ?>
            <?php if ($error): ?><div class="login-err"><?= e($error) ?></div><?php endif; ?>
            <?= f_text('username', 'نام کاربری یا ایمیل', (string) ($_POST['username'] ?? ''), ['required' => true, 'ltr' => true, 'attrs' => 'autofocus autocomplete="username"']) ?>
            <label class="af"><span class="af-label">رمز عبور</span>
                <span class="pass-wrap"><input type="password" name="password" required dir="ltr" autocomplete="current-password" id="pw"><button type="button" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'"><?= icon('eye', 18) ?></button></span>
            </label>
            <button class="abtn abtn-primary abtn-block" type="submit" style="margin-top:6px;padding:13px">ورود <?= icon('arrow-left', 18) ?></button>
        </form>
        <p style="margin:18px 0 0"><a href="<?= e(url('')) ?>" class="muted small">← بازگشت به سایت</a></p>
    </div>
</div>
</body>
</html>
