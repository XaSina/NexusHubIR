<?php
/** @var array $list */
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container rf-page">
    <div class="rf-list-head" data-reveal>
        <h1>🎡 گردونه شانس <?= e(setting('site_title')) ?></h1>
        <p class="muted">بچرخون، جایزه ببر؛ با ثبت‌نام، خرید و دعوت دوستات چرخش بیشتری بگیر.</p>
    </div>
    <?php if (!$list): ?>
        <div class="shop-empty glass" data-reveal><span>🎡</span><b>فعلاً گردونه‌ای فعال نیست</b><p class="muted">به زودی برمی‌گرده؛ کانال تلگرام رو دنبال کن.</p></div>
    <?php else: ?>
    <div class="rf-cards">
        <?php foreach ($list as $i => $w): $pz = array_values(array_filter(wheel_items($w), fn($it) => $it['kind'] === 'prize')); ?>
        <a class="rf-card glass rf-card-active" href="<?= e(wheel_url($w)) ?>" data-reveal style="--d:<?= $i % 8 ?>">
            <span class="rf-card-top"><span class="rf-chip rf-st-active">فعال</span><span class="rf-card-mode">🎡 گردونه شانس</span></span>
            <span class="rf-card-emoji"><?= e($pz[0]['emoji'] ?? '🎁') ?></span>
            <b class="rf-card-title"><?= e($w['title']) ?></b>
            <span class="rf-card-prize">🎁 <?= e(implode('، ', array_slice(array_column($pz, 'label'), 0, 3))) ?></span>
            <span class="btn btn-ghost btn-sm">🎡 بریم بچرخونیم</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
