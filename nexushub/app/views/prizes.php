<?php
/** @var array $member @var array $list */
$tab = in_array($_GET['t'] ?? '', ['raffle', 'wheel', 'discount'], true) ? $_GET['t'] : 'all';
$count = fn($f) => count(array_filter($list, $f));
$tabs = ['all' => ['🎁 همه', $count(fn() => true)], 'raffle' => ['🎰 قرعه‌کشی', $count(fn($p) => $p['source'] === 'raffle')], 'wheel' => ['🎡 گردونه شانس', $count(fn($p) => $p['source'] === 'wheel')],
    'discount' => ['🏷️ کدهای تخفیف', $count(fn($p) => $p['prize_type'] === 'discount')]];
$shown = array_values(array_filter($list, fn($p) => $tab === 'all' || ($tab === 'discount' ? $p['prize_type'] === 'discount' : $p['source'] === $tab)));
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container pz-page">
    <div class="pz-head glass" data-reveal>
        <span class="pz-head-ic">🎁</span>
        <div><h1>جایزه‌های من</h1><small><?= fa_num((string) count($list)) ?> جایزه · <?= fa_num((string) $count(fn($p) => $p['status'] === 'pending')) ?> منتظر دریافت · <?= fa_num((string) $count(fn($p) => $p['status'] === 'active')) ?> کد تخفیف آماده</small></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('notifications')) ?>"><?= icon('bell', 16) ?> اعلان‌ها</a>
    </div>
    <div class="pz-tabs" data-reveal>
        <?php foreach ($tabs as $k => [$t, $n]): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('prizes') . ($k === 'all' ? '' : '?t=' . $k)) ?>"><?= e($t) ?> <em><?= fa_num((string) $n) ?></em></a><?php endforeach; ?>
    </div>
    <?php if (!$shown): ?>
        <div class="shop-empty glass" data-reveal><span>🎁</span><b>هنوز جایزه‌ای اینجا نیست</b><p class="muted">توی قرعه‌کشی‌ها شرکت کن یا گردونه شانس رو بچرخون.</p></div>
    <?php else: ?>
        <div class="pz-grid"><?php foreach ($shown as $i => $p): ?><div data-reveal style="--d:<?= $i % 8 ?>"><?= prize_card_html($p) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
</section>
