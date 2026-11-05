<?php
/** @var array $list */
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container rf-page">
    <div class="rf-list-head" data-reveal>
        <h1>🎰 قرعه‌کشی‌های <?= e(setting('site_title')) ?></h1>
        <p class="muted">شرکت کن، دوستات رو دعوت کن و سر ساعت گردونه‌ی زنده رو ببین.</p>
    </div>
    <?php if (!$list): ?>
        <div class="shop-empty glass" data-reveal><span>🎰</span><b>فعلاً قرعه‌کشی‌ای در جریان نیست</b><p class="muted">به زودی یه قرعه‌کشی جدید داریم؛ کانال تلگرام رو دنبال کن.</p></div>
    <?php else: ?>
    <div class="rf-cards">
        <?php foreach ($list as $i => $r): $st = raffle_stats($r); $pz = raffle_prizes($r); $res = json_decode((string) ($r['results'] ?? ''), true) ?: []; ?>
        <a class="rf-card glass rf-card-<?= e($r['status']) ?>" href="<?= e(raffle_url($r) . ($r['status'] === 'drawn' ? '?show=1' : '')) ?>" data-reveal style="--d:<?= $i % 8 ?>">
            <span class="rf-card-top">
                <span class="rf-chip rf-st-<?= e($r['status']) ?>"><?= e(raffle_status_names()[$r['status']]) ?></span>
                <span class="rf-card-mode"><?= raffle_modes()[$r['mode']][2] ?> <?= e(raffle_modes()[$r['mode']][0]) ?></span>
            </span>
            <span class="rf-card-emoji"><?= e($pz[0]['emoji'] ?? '🎁') ?></span>
            <b class="rf-card-title"><?= e($r['title']) ?></b>
            <span class="rf-card-prize">🎁 <?= e($pz[0]['title']) ?><?= count($pz) > 1 ? ' + ' . fa_num((string) (count($pz) - 1)) . ' جایزه' : '' ?></span>
            <?php if ($r['status'] === 'active'): ?>
                <span class="rf-card-meta">⏰ <?= e(raffle_left_text($st['left'])) ?> مونده · 👥 <?= fa_num((string) $st['participants']) ?><?= $st['need'] ? ' از ' . fa_num((string) $st['need']) : '' ?> نفر</span>
            <?php elseif ($r['status'] === 'drawn' && $res): ?>
                <span class="rf-card-meta">🏆 برنده: <?= e(preg_replace('~\s+\S\.$~u', '', (string) $res[0]['name'])) ?><?= count($res) > 1 ? ' و ' . fa_num((string) (count($res) - 1)) . ' نفر دیگه' : '' ?></span>
                <span class="btn btn-ghost btn-sm">🎡 دیدن گردونه و برنده</span>
            <?php else: ?>
                <span class="rf-card-meta"><?= e(jdate('j F Y', strtotime($r['draw_at']))) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
