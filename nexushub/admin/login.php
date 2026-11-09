<?php
define('NX_ADMIN_PUBLIC', true);
require __DIR__ . '/inc/boot.php';

if (current_user()) redirect('admin/');
// hidden entrance: this page only opens with the one-time ticket handed out by the secret address
if (admin_gate_on() && !admin_gate_ticket_ok()) admin_gate_hide();
$error = '';
$wait = login_blocked();
if (is_post()) {
    $uname = trim((string) ($_POST['username'] ?? ''));
    $acc = $uname !== '' ? row('SELECT id FROM users WHERE username = ? OR email = ?', [$uname, $uname]) : null;
    $accWait = $acc ? rate_over('alogin-acct', 10, 1800, 'u' . $acc['id']) : 0;
    if (!csrf_valid()) $error = 'نشست منقضی شده، دوباره تلاش کنید.';
    elseif ($wait > 0) $error = 'به دلیل تلاش‌های ناموفق، ' . fa_num((string) ceil($wait / 60)) . ' دقیقه دیگر تلاش کنید.';
    elseif ($accWait > 0) $error = 'برای امنیت این حساب، ورود ' . fa_num((string) ceil($accWait / 60)) . ' دقیقه بسته شد.';
    elseif (!captcha_check('admin', (string) ($_POST['captcha'] ?? ''))) $error = 'کد امنیتی درست نیست؛ کد جدید رو وارد کن.';
    elseif (attempt_login($uname, (string) ($_POST['password'] ?? ''))) {
        if ($acc) rate_clear('alogin-acct', 'u' . $acc['id']);
        redirect('admin/');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?><!doctype html>
<html lang="fa" dir="rtl" class="<?= theme_colors()['light'] ? 'theme-light' : 'theme-dark' ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ورود به پنل مدیریت | <?= e(setting('site_title')) ?></title>
<?= favicon_tags() ?>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/captcha.css')) ?>">
<style><?= theme_css_vars() ?></style>
</head>
<body class="adm-body cards-<?= e(isset(card_styles()[(string) setting('card_style', 'glass')]) ? (string) setting('card_style', 'glass') : 'glass') ?>">
<div class="adm-bg" aria-hidden="true"><i></i><i></i></div>
<div class="login-wrap">
    <div class="login-box glass">
        <div class="login-logo"><img src="<?= e(logo_url()) ?>" alt=""></div>
        <h1><?= e(setting('site_title')) ?></h1>
        <p>ورود به پنل مدیریت</p>
        <form method="post">
            <?= csrf_field() ?>
            <?php if ($error): ?><div class="login-err"><?= e($error) ?></div><?php endif; ?>
            <?= f_text('username', 'نام کاربری یا ایمیل', (string) ($_POST['username'] ?? ''), ['required' => true, 'ltr' => true, 'attrs' => 'autofocus autocomplete="username"']) ?>
            <label class="af"><span class="af-label">رمز عبور</span>
                <span class="pass-wrap"><input type="password" name="password" required dir="ltr" autocomplete="current-password" id="pw"></span>
            </label>
            <?= captcha_html('admin') ?>
            <button class="abtn abtn-primary abtn-block" type="submit" style="margin-top:6px;padding:13px">ورود <?= icon('arrow-left', 18) ?></button>
        </form>
        <p style="margin:18px 0 0"><a href="<?= e(url('')) ?>" class="muted small">← بازگشت به سایت</a></p>
    </div>
</div>
<script src="<?= e(asset('js/pw.js')) ?>"></script>
<script src="<?= e(asset('js/btnfx.js')) ?>"></script>
<script src="<?= e(asset('js/captcha.js')) ?>"></script>
</body>
</html>
