<?php
$since = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) setting('trending_days', 30)) . ' days'));
$trend = get_posts(['order' => 'views', 'limit' => 5, 'since' => $since]);
if (count($trend) < 3) $trend = get_posts(['order' => 'views', 'limit' => 5]);
$tags = popular_tags(16);
$tgLink = safe_link((string) setting('social_telegram'));
?>
<aside class="sidebar"><div class="sb-inner">
    <section class="widget glass" data-reveal>
        <h3 class="w-title"><?= icon('fire', 18) ?> داغ‌ترین‌ها</h3>
        <div class="rank-list">
            <?php foreach ($trend as $i => $p) partial('card', ['p' => $p, 'variant' => 'rank', 'i' => $i]); ?>
        </div>
    </section>

    <?= ad_slot('sidebar') ?>

    <?php if ($tgLink): ?>
    <section class="widget widget-tg glass" data-reveal>
        <div class="wtg-head">
            <span class="wtg-logo"><img src="<?= e(media_url((string) setting('logo'))) ?>" alt="" width="56" height="56"><i class="wtg-badge"><?= icon('telegram', 14) ?></i></span>
            <div><b><?= e(setting('site_title')) ?></b><small><?= e(telegram_handle()) ?></small></div>
        </div>
        <p><?= e(setting('site_tagline')) ?></p>
        <a class="btn btn-primary btn-block" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> عضویت در کانال</a>
    </section>
    <?php endif; ?>

    <?php if ($tags): ?>
    <section class="widget glass" data-reveal>
        <h3 class="w-title"><?= icon('tag', 18) ?> برچسب‌های داغ</h3>
        <div class="tag-cloud">
            <?php foreach ($tags as $t): ?><a href="<?= e(tag_url($t)) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div></aside>
