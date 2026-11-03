<?php
/** @var array $member @var array $errors @var string $tab @var string $flash @var array $old */
$m = $member;
$v = fn($k) => e($old[$k] ?? $m[$k] ?? '');
$err = fn($k) => !empty($errors[$k]) ? '<span class="field-err">' . e($errors[$k]) . '</span>' : '';
$ec = fn($k) => !empty($errors[$k]) ? ' class="is-err"' : '';
$suggest = get_posts(['limit' => 3]);
?>
<section class="container acc-wrap">
    <div class="acc-hero">
        <div class="mcard tilt" id="mcard">
            <span class="mcard-holo" aria-hidden="true"></span><span class="mcard-shine" aria-hidden="true"></span>
            <div class="mcard-top">
                <span class="mcard-brand"><img src="<?= e(media_url((string) setting('logo'))) ?>" alt="">NEXUS</span>
                <span class="mcard-tag">MEMBER</span>
            </div>
            <span class="mcard-chip" aria-hidden="true"></span>
            <div class="mcard-code" data-copy="<?= e($m['member_code']) ?>" title="کپی کد عضویت" data-scramble="<?= e($m['member_code']) ?>"><?= e($m['member_code']) ?></div>
            <div class="mcard-bottom">
                <div><small>CARD HOLDER</small><b><?= e(member_full_name($m)) ?></b></div>
                <div><small>MEMBER SINCE</small><b><?= jdate('Y/m/d', $m['created_at']) ?></b></div>
            </div>
        </div>

        <div class="acc-welcome glass" data-reveal>
            <span class="eyebrow"><?= icon('sparkle', 15) ?> حساب کاربری NEXUS</span>
            <h1>سلام <?= e($m['first_name']) ?> 👋</h1>
            <p>به کامیونیتی <b class="grad-text">NEXUS | HUB</b> خوش اومدی. کد عضویت اختصاصی تو <b dir="ltr"><?= e($m['member_code']) ?></b> است؛ برای شرکت در قرعه‌کشی‌ها و رویدادها از همین کد استفاده کن.</p>
            <div class="acc-stats">
                <div class="acc-stat"><b><?= fa_num((string) member_days($m)) ?></b><small>روز عضویت</small></div>
                <div class="acc-stat"><b><?= fa_num((string) max(1, (int) $m['login_count'])) ?></b><small>دفعات ورود</small></div>
                <div class="acc-stat"><b><?= $m['last_login'] ? jdate('j F', $m['last_login']) : '—' ?></b><small>آخرین ورود</small></div>
            </div>
            <div class="acc-actions">
                <?php if ($tg = safe_link((string) setting('social_telegram'))): ?><a class="btn btn-primary" href="<?= e($tg) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> کانال تلگرام</a><?php endif; ?>
                <button class="btn btn-ghost" type="button" data-copy="<?= e($m['member_code']) ?>"><?= icon('copy', 17) ?> کپی کد عضویت</button>
                <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="act" value="logout"><button class="btn btn-ghost" type="submit"><?= icon('arrow-right', 17) ?> خروج</button></form>
            </div>
        </div>
    </div>

    <?php if ($flash): ?><div class="alert alert-success acc-flash"><?= e($flash) ?></div><?php endif; ?>
    <?php if (!empty($errors['_'])): ?><div class="alert alert-error acc-flash"><?= e($errors['_']) ?></div><?php endif; ?>

    <div class="acc-tabs" id="accTabs">
        <button type="button" data-tab="info" class="<?= $tab === 'info' ? 'active' : '' ?>">👤 اطلاعات من</button>
        <button type="button" data-tab="edit" class="<?= $tab === 'edit' ? 'active' : '' ?>">✏️ ویرایش اطلاعات</button>
        <button type="button" data-tab="password" class="<?= $tab === 'password' ? 'active' : '' ?>">🔐 تغییر رمز</button>
    </div>

    <div class="acc-pane glass" data-pane="info" <?= $tab !== 'info' ? 'hidden' : '' ?>>
        <div class="acc-info">
            <div class="acc-info-item"><span class="ii"><?= icon('user', 20) ?></span><div><small>نام و نام خانوادگی</small><b><?= e(member_full_name($m)) ?></b></div></div>
            <div class="acc-info-item"><span class="ii"><?= icon('sparkle', 20) ?></span><div><small>کد عضویت</small><b dir="ltr" style="text-align:right"><?= e($m['member_code']) ?></b></div></div>
            <div class="acc-info-item"><span class="ii"><?= icon('tag', 20) ?></span><div><small>نام کاربری</small><b dir="ltr" style="text-align:right">@<?= e($m['username']) ?></b></div></div>
            <div class="acc-info-item"><span class="ii"><?= icon('whatsapp', 20) ?></span><div><small>شماره موبایل</small><b dir="ltr" style="text-align:right"><?= e(fa_num($m['phone'])) ?></b></div></div>
            <div class="acc-info-item"><span class="ii"><?= icon('mail', 20) ?></span><div><small>ایمیل</small><b dir="ltr" style="text-align:right"><?= e($m['email']) ?></b></div></div>
            <div class="acc-info-item"><span class="ii"><?= icon('calendar', 20) ?></span><div><small>تاریخ عضویت</small><b><?= jdate('l j F Y', $m['created_at']) ?></b></div></div>
        </div>
    </div>

    <form class="acc-pane glass acc-form" data-pane="edit" method="post" <?= $tab !== 'edit' ? 'hidden' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="act" value="profile">
        <div class="form-row">
            <label class="field"><span>نام</span><input name="first_name" value="<?= $v('first_name') ?>" required maxlength="60"<?= $ec('first_name') ?>><?= $err('first_name') ?></label>
            <label class="field"><span>نام خانوادگی</span><input name="last_name" value="<?= $v('last_name') ?>" required maxlength="60"<?= $ec('last_name') ?>><?= $err('last_name') ?></label>
        </div>
        <div class="form-row">
            <label class="field"><span>شماره موبایل</span><input name="phone" value="<?= $v('phone') ?>" dir="ltr" inputmode="tel" required<?= $ec('phone') ?>><?= $err('phone') ?></label>
            <label class="field"><span>ایمیل</span><input name="email" type="email" value="<?= $v('email') ?>" dir="ltr" required<?= $ec('email') ?>><?= $err('email') ?></label>
        </div>
        <label class="field"><span>نام کاربری (قابل تغییر نیست)</span><input value="@<?= e($m['username']) ?>" dir="ltr" disabled></label>
        <button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> ذخیره تغییرات</button>
    </form>

    <form class="acc-pane glass acc-form" data-pane="password" method="post" <?= $tab !== 'password' ? 'hidden' : '' ?>>
        <?= csrf_field() ?><input type="hidden" name="act" value="password">
        <label class="field"><span>رمز فعلی</span><input type="password" name="current_password" dir="ltr" required autocomplete="current-password"<?= $ec('current_password') ?>><?= $err('current_password') ?></label>
        <div class="form-row">
            <label class="field"><span>رمز جدید</span><input type="password" name="new_password" dir="ltr" required minlength="8" autocomplete="new-password"<?= $ec('new_password') ?>><?= $err('new_password') ?></label>
            <label class="field"><span>تکرار رمز جدید</span><input type="password" name="confirm_password" dir="ltr" required minlength="8" autocomplete="new-password"<?= $ec('confirm_password') ?>><?= $err('confirm_password') ?></label>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> تغییر رمز</button>
    </form>

    <?php if ($suggest): ?>
    <div class="sec-head" data-reveal><h2 class="sec-title"><span class="sec-icon"><?= icon('bolt', 20) ?></span> تازه‌ترین‌ها برای تو</h2><a class="sec-more" href="<?= e(url('archive')) ?>">همه اخبار <?= icon('arrow-left', 16) ?></a></div>
    <div class="grid-cards">
        <?php foreach ($suggest as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
    </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.querySelectorAll('#accTabs [data-tab]');
    tabs.forEach(function (b) {
        b.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.toggle('active', x === b); });
            document.querySelectorAll('.acc-pane').forEach(function (p) { p.hidden = p.dataset.pane !== b.dataset.tab; });
        });
    });
});
</script>
