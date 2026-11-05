<?php
/** @var array $wheel */
$w = $wheel;
$me = current_member();
$S = wheel_state($w, $me);
$R = $S['rules'];
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/luck.css')) ?>">
<section class="container rf-page lw-page" id="lwPage">
    <div class="rf-hero lw-hero rf-in">
        <div class="rf-hero-bg" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="rf-hero-in">
            <?php if ($w['image']): ?><div class="rf-hero-art"><img src="<?= e(media_url($w['image'])) ?>" alt="<?= e($w['title']) ?>"></div><?php endif; ?>
            <span class="rf-chip rf-st-active">گردونه شانس</span>
            <h1 class="rf-title"><?= e($w['title']) ?></h1>
            <?php if (trim((string) $w['description']) !== ''): ?><p class="rf-mode-line"><?= nl2br(e(str_excerpt((string) $w['description'], 220))) ?></p><?php endif; ?>
            <?php if (!empty($w['end_at'])): ?><p class="rf-when"><?= icon('calendar', 16) ?> تا <?= e(jdate('l j F — ساعت H:i', strtotime($w['end_at']))) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="rf-grid lw-grid">
        <div class="rf-main">
            <div class="rf-wheel-card glass lw-card rf-in" style="--d:2">
                <div class="rf-wheel-head"><b>🎡 بچرخون و جایزه ببر</b><span class="lw-left" id="lwLeftChip"></span></div>
                <div class="nxw" id="lwWheel" data-logo="<?= e(logo_url()) ?>"></div>
                <button type="button" class="lw-spin" id="lwSpin"><span class="lw-spin-glow" aria-hidden="true"></span><span class="lw-spin-ic">🎡</span><span class="lw-spin-t" id="lwSpinT">بچرخون!</span></button>
                <p class="rf-wheel-note" id="lwNote"></p>
                <div class="lw-last" id="lwLast"></div>
            </div>
        </div>

        <aside class="rf-side">
            <div class="rf-act glass lw-mine rf-in" style="--d:3" id="lwMine"></div>
            <div class="rf-how glass rf-in" style="--d:4">
                <h3>🎟️ چطوری چرخش بگیرم؟</h3>
                <div class="lw-rules" id="lwRules"></div>
            </div>
            <div class="rf-people glass rf-in" style="--d:5">
                <h3>🎁 جایزه‌های گردونه</h3>
                <div class="lw-items" id="lwItems"></div>
            </div>
            <div class="rf-people glass rf-in" style="--d:6" id="lwRecentBox">
                <h3>🏆 آخرین برنده‌ها</h3>
                <div class="lw-feed" id="lwRecent"></div>
            </div>
        </aside>
    </div>
</section>
<script>window.NX_WHEEL = <?= json_encode($S + ['slug' => $w['slug']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('js/wheel.js')) ?>" defer></script>
<script src="<?= e(asset('js/luckwheel.js')) ?>" defer></script>
