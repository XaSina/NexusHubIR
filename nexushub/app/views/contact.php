<?php /** @var bool $sent @var array $errors @var array $old */ ?>
<section class="container page-hero">
    <div class="ph-inner glass" data-reveal>
        <span class="ph-icon">✉️</span>
        <div><h1 class="ph-title">تماس با ما</h1><p class="ph-sub">پیشنهاد، انتقاد، همکاری یا سفارش تبلیغ؟ پیام بده 👇</p></div>
    </div>
</section>
<section class="container contact-grid">
    <form class="glass contact-form" method="post" data-reveal>
        <?= csrf_field() ?>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <?php if ($sent): ?><div class="alert alert-success">✅ پیام شما با موفقیت ارسال شد. به زودی پاسخ می‌دهیم.</div><?php endif; ?>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <div class="form-row">
            <label class="field"><span>نام *</span><input name="name" value="<?= e($old['name']) ?>" required maxlength="120"></label>
            <label class="field"><span>ایمیل</span><input name="email" type="email" value="<?= e($old['email']) ?>" dir="ltr" maxlength="190"></label>
        </div>
        <label class="field"><span>موضوع</span><input name="subject" value="<?= e($old['subject']) ?>" maxlength="190"></label>
        <label class="field"><span>پیام *</span><textarea name="body" rows="6" required><?= e($old['body']) ?></textarea></label>
        <button class="btn btn-primary btn-lg" type="submit"><?= icon('send', 18) ?> ارسال پیام</button>
    </form>
    <?php $cItems = [];
    foreach (social_links() as $sl) $cItems[] = ['url' => $sl['url'], 'icon' => icon($sl['icon'], 26), 'label' => $sl['label'], 'sub' => preg_replace('~^https?://~', '', $sl['url']), 'blank' => true, 'k' => $sl['icon']];
    if ($adm = safe_link((string) setting('social_admin'))) $cItems[] = ['url' => $adm, 'icon' => '👑', 'label' => 'ارتباط با مدیر', 'sub' => preg_replace('~^https?://~', '', $adm), 'blank' => true, 'k' => 'admin'];
    if ($em = (string) setting('contact_email')) $cItems[] = ['url' => 'mailto:' . $em, 'icon' => icon('mail', 26), 'label' => 'ایمیل', 'sub' => $em, 'blank' => false, 'k' => 'mail'];
    $iconsOnly = setting('contact_style', 'icons') !== 'cards'; ?>
    <div class="contact-side<?= $iconsOnly ? ' cs-icons' : '' ?>">
        <?php if ($iconsOnly): ?>
            <div class="cs-icon-row glass" data-reveal>
                <small><?= e(setting('contact_icons_title', 'از این راه‌ها هم در دسترسیم')) ?></small>
                <div class="cs-icon-list">
                <?php foreach ($cItems as $i => $c): ?>
                    <a class="cs-ic cs-<?= e($c['k']) ?>" href="<?= e($c['url']) ?>"<?= $c['blank'] ? ' target="_blank" rel="noopener"' : '' ?> title="<?= e($c['label']) ?>" aria-label="<?= e($c['label']) ?>" style="--i:<?= $i ?>"><?= $c['icon'] ?></a>
                <?php endforeach; ?>
                </div>
            </div>
        <?php else: foreach ($cItems as $i => $c): ?>
            <a class="contact-card glass tilt" href="<?= e($c['url']) ?>"<?= $c['blank'] ? ' target="_blank" rel="noopener"' : '' ?> data-reveal style="--d:<?= $i ?>">
                <span class="cc-icon"><?= $c['icon'] ?></span><span><b><?= e($c['label']) ?></b><small dir="ltr"><?= e($c['sub']) ?></small></span>
            </a>
        <?php endforeach; endif; ?>
    </div>
</section>
