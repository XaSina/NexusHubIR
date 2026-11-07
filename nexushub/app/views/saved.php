<?php
/** @var array $posts @var array $pg @var int $total */
?>
<section class="container page-hero bk-hero">
    <div class="ph-inner glass" data-reveal>
        <span class="ph-icon">🔖</span>
        <div>
            <h1 class="ph-title">ذخیره‌شده‌های من</h1>
            <p class="ph-sub"><?= $total ? fa_num((string) $total) . ' خبر برای بعد نگه داشتی' : 'هر خبری رو با دکمه‌ی 🔖 ذخیره کنی، اینجا جمع می‌شه' ?></p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('account')) ?>"><?= icon('user', 16) ?> حساب من</a>
    </div>
</section>
<section class="container">
    <?php if ($posts): ?>
        <div class="grid-cards bk-grid" id="bkGrid">
            <?php foreach ($posts as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
        </div>
        <?= pagination_html($pg, url('saved')) ?>
    <?php endif; ?>
    <div class="empty glass bk-empty" data-reveal<?= $posts ? ' hidden' : '' ?>>
        <span class="bk-empty-ic"><svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4.2-6.5 4.2v-16a1 1 0 0 1 1-1Z"/></svg></span>
        <b>هنوز چیزی ذخیره نکردی</b>
        <p class="muted">روی هر خبر دکمه‌ی «ذخیره» رو بزن تا بعداً راحت پیداش کنی.</p>
        <a class="btn btn-primary" href="<?= e(url('archive')) ?>">📰 دیدن اخبار</a>
    </div>
</section>
