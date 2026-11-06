<?php
/** @var array $list @var int $page @var int $pages */
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container pz-page">
    <div class="pz-head glass" data-reveal>
        <span class="pz-head-ic">🔔</span>
        <div><h1>اعلان‌های من</h1><small>پیام‌های سایت، سفارش‌ها، جایزه‌ها و پیام‌های مدیر</small></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('prizes')) ?>">🎁 جایزه‌های من</a>
    </div>
    <?php if (!$list): ?>
        <div class="shop-empty glass" data-reveal><span>🔔</span><b>اعلانی نداری</b><p class="muted">هر خبری بشه همین‌جا می‌بینیش.</p></div>
    <?php else: ?>
    <div class="nt-list glass" data-reveal>
        <?php foreach ($list as $n): $link = (string) $n['link']; $tag = $link !== '' ? 'a' : 'div'; ?>
        <<?= $tag ?> class="nt-item nt-<?= e($n['tone']) ?><?= $n['is_read'] ? '' : ' is-new' ?>"<?= $link !== '' ? ' href="' . e(url($link)) . '"' : '' ?>>
            <span class="nt-ic"><?= e($n['icon']) ?></span>
            <span class="nt-t"><b><?= e($n['title']) ?></b><?php if ((string) $n['body'] !== ''): ?><small><?= e($n['body']) ?></small><?php endif; ?></span>
            <em class="nt-ago"><?= e(jago($n['updated_at'])) ?></em>
        </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?><div class="pz-pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e(url('notifications') . '?p=' . $i) ?>"><?= fa_num((string) $i) ?></a><?php endfor; ?></div><?php endif; ?>
    <?php endif; ?>
</section>
