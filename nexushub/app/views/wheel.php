<?php
/** @var array $wheel */
$w = $wheel;
$me = current_member();
$S = wheel_state($w, $me);
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/luck.css')) ?>">
<section class="container rf-page lw-page" id="lwPage">
    <div class="lw-head glass rf-in">
        <?php if ($w['image']): ?><span class="lw-head-art"><img src="<?= e(media_url($w['image'])) ?>" alt=""></span><?php endif; ?>
        <div class="lw-head-t">
            <span class="rf-chip rf-st-active">گردونه شانس</span>
            <h1><?= e($w['title']) ?></h1>
            <?php if (trim((string) $w['description']) !== ''): ?><div class="nx-clamp" data-clamp><p><?= nl2br(e(trim((string) $w['description']))) ?></p><button type="button" class="nx-more" hidden><span>بیشتر</span> <?= icon('chevron-down', 14) ?></button></div><?php endif; ?>
        </div>
        <?php if (!empty($w['end_at'])): ?><span class="lw-head-end"><?= icon('calendar', 15) ?> تا <?= e(jdate('j F — H:i', strtotime($w['end_at']))) ?></span><?php endif; ?>
    </div>

    <div class="lw-grid">
        <div class="lw-wheelcard glass rf-in" style="--d:2">
            <div class="lw-wc-head"><b>🎡 بچرخون و جایزه ببر</b><span class="lw-left" id="lwLeftChip"></span></div>
            <div class="nxw" id="lwWheel"></div>
            <button type="button" class="lw-spin" id="lwSpin"><span class="lw-spin-glow" aria-hidden="true"></span><span class="lw-spin-ic">🎡</span><span class="lw-spin-t" id="lwSpinT">بچرخون!</span></button>
            <p class="rf-wheel-note" id="lwNote"></p>
            <div class="lw-last" id="lwLast"></div>
        </div>
        <aside class="lw-side">
            <div class="lw-box glass rf-in" style="--d:3" id="lwMine"></div>
            <div class="lw-box glass rf-in" style="--d:4"><h3>🎟️ چطوری چرخش بگیرم؟</h3><div class="lw-rules" id="lwRules"></div></div>
            <div class="lw-box glass rf-in" style="--d:5"><h3>🎁 جایزه‌های گردونه</h3><div class="lw-items" id="lwItems"></div></div>
        </aside>
    </div>
</section>
<script>window.NX_WHEEL = <?= json_encode($S + ['slug' => $w['slug']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('js/wheel.js')) ?>" defer></script>
<script src="<?= e(asset('js/luckwheel.js')) ?>" defer></script>
