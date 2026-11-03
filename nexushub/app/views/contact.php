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
    <div class="contact-side">
        <?php foreach (social_links() as $i => $s): ?>
            <a class="contact-card glass tilt" href="<?= e($s['url']) ?>" target="_blank" rel="noopener" data-reveal style="--d:<?= $i ?>">
                <span class="cc-icon"><?= icon($s['icon'], 24) ?></span>
                <span><b><?= e($s['label']) ?></b><small dir="ltr"><?= e(preg_replace('~^https?://~', '', $s['url'])) ?></small></span>
            </a>
        <?php endforeach; ?>
        <?php if ($adm = safe_link((string) setting('social_admin'))): ?>
            <a class="contact-card glass tilt" href="<?= e($adm) ?>" target="_blank" rel="noopener" data-reveal>
                <span class="cc-icon">👑</span><span><b>ارتباط با مدیر</b><small dir="ltr"><?= e(preg_replace('~^https?://~', '', $adm)) ?></small></span>
            </a>
        <?php endif; ?>
        <?php if (setting('contact_email')): ?>
            <a class="contact-card glass tilt" href="mailto:<?= e(setting('contact_email')) ?>" data-reveal>
                <span class="cc-icon"><?= icon('mail', 24) ?></span><span><b>ایمیل</b><small dir="ltr"><?= e(setting('contact_email')) ?></small></span>
            </a>
        <?php endif; ?>
    </div>
</section>
