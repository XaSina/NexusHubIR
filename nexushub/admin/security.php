<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

if (is_post()) {
    csrf_check();
    $act = (string) ($_POST['act'] ?? 'save');
    if ($act === 'newkey') {
        $_SESSION['gate_show'] = admin_gate_reset();
        flash('🔑 لینک ورود مخفی عوض شد؛ لینک قبلی دیگه کار نمی‌کنه. لینک جدید پایین نوشته شده — همین الان ذخیره‌ش کن.');
        redirect('admin/security.php');
    }
    if ($act === 'clearlog') { save_settings(['admin_login_log' => '[]']); flash('فهرست ورودها پاک شد.'); redirect('admin/security.php'); }
    $save = [];
    foreach (['captcha_site', 'captcha_admin', 'admin_gate_on'] as $k) if (isset($_POST[$k])) $save[$k] = $_POST[$k] === '1' ? '1' : '0';
    $save['captcha_len'] = (string) max(4, min(6, (int) ($_POST['captcha_len'] ?? 5)));
    $save['admin_idle_min'] = (string) max(10, min(1440, (int) ($_POST['admin_idle_min'] ?? 120)));
    if (($save['admin_gate_on'] ?? '0') === '1' && admin_gate_hash() === '') $_SESSION['gate_show'] = admin_gate_reset();   // turning it on with no link yet: make one
    save_settings($save);
    flash('تنظیمات امنیت ذخیره شد ✅');
    redirect('admin/security.php');
}

$gateShow = (string) ($_SESSION['gate_show'] ?? ''); unset($_SESSION['gate_show']);
$unlock = is_file(NX_APP . '/admin-unlock.txt');
$log = json_decode((string) setting('admin_login_log', '[]'), true) ?: [];
$me = current_user();
$checks = [
    ['ورود مخفی پنل', admin_gate_on(), 'آدرس ثابت ورود پنل برای بقیه وجود نداره'],
    ['کد امنیتی ورود پنل', setting_on('captcha_admin'), 'ربات‌ها نمی‌تونن رمز حدس بزنن'],
    ['کد امنیتی ورود و ثبت‌نام سایت', setting_on('captcha_site'), 'جلوی ساخت حساب و حدس رمز با ربات'],
    ['اتصال امن (https)', is_https(), 'رمزها و اطلاعات رمزنگاری‌شده منتقل می‌شن'],
    ['https برای گوگل', setting_on('seo_https'), 'تنظیمات ← سئو'],
    ['نام کاربری پنل «admin» نیست', strtolower((string) $me['username']) !== 'admin', 'اسم‌های رایج مثل admin اولین حدس ربات‌هاست'],
    ['پوشه‌ی نصب بسته‌ست', is_file(NX_APP . '/installed.lock'), 'نصب‌کننده دوباره اجرا نمی‌شه'],
    ['نمایش خطاها خاموشه', !config('debug'), 'جزئیات فنی به بازدیدکننده نشون داده نمی‌شه'],
];
$score = (int) round(count(array_filter($checks, fn($c) => $c[1])) / count($checks) * 100);
adm_head('امنیت', 'security');
?>
<form method="post" class="sec-page">
<?= csrf_field() ?><input type="hidden" name="act" value="save">

<div class="card sec-score" data-l="<?= $score >= 85 ? 'good' : ($score >= 60 ? 'mid' : 'low') ?>">
    <div class="ss-ring"><svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="27"/><circle class="ss-bar" cx="32" cy="32" r="27" style="stroke-dashoffset:<?= round(169.6 * (1 - $score / 100), 1) ?>"/></svg><b><?= fa_num((string) $score) ?>٪</b></div>
    <div class="ss-txt"><h3>🛡️ وضعیت امنیت سایت</h3><small>هر مورد سبز یعنی یه راه حمله بسته شده.</small>
        <ul class="ss-list"><?php foreach ($checks as [$t, $ok, $why]): ?><li class="<?= $ok ? 'ok' : 'no' ?>"><i><?= $ok ? '✓' : '!' ?></i><span><b><?= e($t) ?></b><small><?= e($why) ?></small></span></li><?php endforeach; ?></ul>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>🚪 ورود مخفی پنل مدیریت</h3></div>
    <p class="muted small sec-p">وقتی روشن باشه، آدرس <code dir="ltr">/admin</code> و هر آدرس دیگه برای همه «صفحه پیدا نشد» (۴۰۴) نشون می‌ده. پنل فقط با لینک مخفی ۴۸ حرفی باز می‌شه و با هر بار باز کردن <b>و هر رفرش</b>، یه آدرس ورود ۹۶ حرفی <b>تازه و یک‌بار مصرف</b> ساخته می‌شه که فقط توی همون مرورگر کار می‌کنه.</p>
    <?php if ($gateShow !== ''): ?>
    <div class="sec-link">
        <small>🔑 لینک ورود مخفی جدید — فقط همین یه بار نشون داده می‌شه، همین الان کپی و یه جای امن ذخیره‌ش کن:</small>
        <div class="sl-row"><code dir="ltr" id="gateUrl"><?= e($gateShow) ?></code><button type="button" class="abtn abtn-primary abtn-sm" data-copy="<?= e($gateShow) ?>"><?= icon('copy', 15) ?> کپی</button></div>
    </div>
    <?php else: ?>
    <div class="sec-link sec-link-hidden"><small>🔒 <?= admin_gate_hash() !== '' ? 'لینک فعلی برای امنیت هیچ‌جا ذخیره یا نوشته نمی‌شه (فقط اثر انگشتش نگه داشته می‌شه). همونیه که ذخیره کردی؛ اگه گمش کردی، «ساخت لینک جدید» رو بزن.' : 'هنوز لینکی ساخته نشده؛ «ساخت لینک جدید» رو بزن.' ?></small></div>
    <?php endif; ?>
    <?php if ($unlock): ?><div class="alert alert-warn">⚠️ فایل <code>app/admin-unlock.txt</code> وجود داره، پس ورود مخفی الان خاموشه. بعد از ورود، اون فایل رو از هاست پاک کن.</div><?php endif; ?>
    <?= f_switch('admin_gate_on', 'ورود مخفی روشن باشه', setting_on('admin_gate_on'), 'اول لینک بالا رو ذخیره کن، بعد روشنش کن') ?>
    <div class="grid g2">
        <div class="sec-newkey"><button type="submit" name="act" value="newkey" class="abtn abtn-ghost" data-confirm="لینک ورود مخفی عوض بشه؟ لینک قبلی دیگه کار نمی‌کنه.">🔑 ساخت لینک جدید</button></div>
    </div>
    <details class="sec-help"><summary>🆘 اگه لینک مخفی رو گم کردم چی؟</summary>
        <p>توی cPanel ← File Manager، داخل پوشه‌ی <code dir="ltr">public_html/app</code> یه فایل خالی به اسم <code dir="ltr">admin-unlock.txt</code> بساز. تا وقتی این فایل هست، پنل مثل قبل از <code dir="ltr">/admin/login.php</code> باز می‌شه. وارد شو، لینک جدید بگیر و بعد اون فایل رو پاک کن.</p>
    </details>
</div>

<div class="card">
    <div class="card-head"><h3>🔢 کد امنیتی (کپچا)</h3></div>
    <p class="muted small sec-p">یه تصویر با عددهای کج و نویزدار که روی همین سرور ساخته می‌شه (به سرویس خارجی وصل نیست). هر کد فقط یه بار و تا ۱۰ دقیقه اعتبار داره؛ با هر اشتباه، کد عوض می‌شه.</p>
    <?= f_switch('captcha_admin', 'کد امنیتی برای ورود پنل', setting_on('captcha_admin')) ?>
    <?= f_switch('captcha_site', 'کد امنیتی برای ورود و ثبت‌نام سایت', setting_on('captcha_site')) ?>
    <div class="grid g2">
        <?= f_select('captcha_len', 'تعداد رقم کد', ['4' => '۴ رقم', '5' => '۵ رقم (پیشنهادی)', '6' => '۶ رقم'], (string) setting('captcha_len', 5)) ?>
        <?= f_text('admin_idle_min', 'خروج خودکار از پنل بعد از چند دقیقه بی‌کاری', (string) setting('admin_idle_min', 120), ['type' => 'number', 'min' => 10, 'max' => 1440]) ?>
    </div>
    <p class="muted small sec-p">🔒 خودکار و همیشه فعال: قفل ۱۵ دقیقه‌ای بعد از ۵ رمز اشتباه از یه اینترنت، قفل ۳۰ دقیقه‌ای خود حساب بعد از ۱۰ رمز اشتباه از هر جا، محدودیت ثبت‌نام (۶ حساب در ساعت از یه اینترنت)، و بسته شدن نشست پنل اگه مرورگر عوض بشه.</p>
</div>

<div class="card">
    <div class="card-head"><h3>🕵️ آخرین ورودها به پنل</h3><?php if ($log): ?><button type="submit" name="act" value="clearlog" class="abtn abtn-ghost abtn-sm" data-confirm="فهرست پاک بشه؟">پاک کردن</button><?php endif; ?></div>
    <?php if (!$log): ?><div class="empty-state"><span>🕵️</span>هنوز ورودی ثبت نشده.</div><?php else: ?>
    <div class="sec-log">
        <?php foreach ($log as $l): ?>
        <div class="slg <?= !empty($l['ok']) ? 'ok' : 'bad' ?>"><i><?= !empty($l['ok']) ? '✓' : '✕' ?></i>
            <span><b><?= e($l['u'] ?? '') ?></b> <?= !empty($l['ok']) ? 'وارد شد' : 'رمز اشتباه زد' ?><small><span dir="ltr"><?= e($l['ip'] ?? '') ?></span> · <?= e(ua_short((string) ($l['ua'] ?? ''))) ?></small></span>
            <em><?= e(jago((string) ($l['at'] ?? now()))) ?></em></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="sticky-save"><button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره‌ی تنظیمات امنیت</button></div>
</form>
<?php adm_foot();
