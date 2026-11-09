<?php
// every part of the sidebar, its order and its texts come from Panel → Appearance → Home layout
$tgLink = safe_link((string) setting('social_telegram'));
?>
<?php ob_start(); foreach (sidebar_widgets() as $wd): if (empty($wd['on'])) continue; switch ($wd['key']):

case 'trend':
    $since = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) setting('trending_days', 30)) . ' days'));
    $n = max(3, min(10, (int) setting('sb_trend_count', 5)));
    $trend = get_posts(['order' => 'views', 'limit' => $n, 'since' => $since]);
    if (count($trend) < 3) $trend = get_posts(['order' => 'views', 'limit' => $n]);
    if (!$trend) break; ?>
    <section class="widget glass" data-reveal>
        <h3 class="w-title"><?= icon('fire', 18) ?> <?= e(setting('sb_trend_title') ?: 'داغ‌ترین‌ها') ?></h3>
        <div class="rank-list">
            <?php foreach ($trend as $i => $p) partial('card', ['p' => $p, 'variant' => 'rank', 'i' => $i]); ?>
        </div>
    </section>
<?php break;

case 'ads': echo ad_slot('sidebar'); break;

case 'telegram': if (!$tgLink) break; ?>
    <section class="widget widget-tg glass" data-reveal>
        <div class="wtg-head">
            <span class="wtg-logo"><img src="<?= e(logo_url()) ?>" alt="" width="56" height="56"><i class="wtg-badge"><?= icon('telegram', 14) ?></i></span>
            <div><b><?= e(setting('sb_tg_title') ?: setting('site_title')) ?></b><small><?= e(telegram_handle()) ?></small></div>
        </div>
        <?php $tgText = (string) (setting('sb_tg_text') ?: setting('site_tagline')); if ($tgText !== ''): ?><p><?= e($tgText) ?></p><?php endif; ?>
        <a class="btn btn-primary btn-block" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> <?= e(setting('sb_tg_btn') ?: 'عضویت در کانال') ?></a>
    </section>
<?php break;

case 'tags':
    $tags = popular_tags(max(4, min(40, (int) setting('sb_tags_count', 16))));
    if (!$tags) break; ?>
    <section class="widget glass" data-reveal>
        <h3 class="w-title"><?= icon('tag', 18) ?> <?= e(setting('sb_tags_title') ?: 'برچسب‌های داغ') ?></h3>
        <div class="tag-cloud">
            <?php foreach ($tags as $t): ?><a href="<?= e(tag_url($t)) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
    </section>
<?php break;

endswitch; endforeach;
$sbHtml = trim((string) ob_get_clean());
if ($sbHtml !== '') echo '<aside class="sidebar"><div class="sb-inner">' . $sbHtml . '</div></aside>';   // nothing switched on = no empty column
