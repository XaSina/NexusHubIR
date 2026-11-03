<?php
$sections = setting_json('home_sections');
if (!$sections) $sections = json_decode(default_settings()['home_sections'], true);
$heroStyle = (string) setting('hero_style', 'magazine');
// featured posts always come first in the top section; remaining slots are filled with the latest news
$heroNeed = $heroStyle === 'grid' ? 3 : 5;
$featured = get_posts(['featured' => 1, 'limit' => $heroNeed]);
if (count($featured) < $heroNeed) {
    $featured = array_merge($featured, get_posts(['limit' => $heroNeed - count($featured), 'exclude' => array_column($featured, 'id') ?: [0]]));
}
$heroIds = array_column($featured, 'id');
$cats = get_categories(true);
$tgLink = safe_link((string) setting('social_telegram'));
$middleAdDone = false;
?>

<?php $stories = setting_on('show_stories') ? get_active_stories() : []; if ($stories): ?>
<section class="container stories" aria-label="استوری‌ها">
    <div class="stories-track">
        <?php foreach ($stories as $i => $s): ?>
            <button type="button" class="story" data-story="<?= $i ?>" data-sid="<?= (int) $s['id'] ?>" data-v="<?= (int) $s['v'] ?>" style="--i:<?= $i ?>">
                <span class="story-ring"><span class="story-inner"><img src="<?= e($s['cover']) ?>" alt="" loading="lazy" decoding="async"></span></span>
                <span class="story-name"><?= e($s['title']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</section>
<div class="sv" id="storyViewer" hidden aria-modal="true" role="dialog">
    <div class="sv-frame">
        <div class="sv-bars"></div>
        <div class="sv-head">
            <span class="sv-av"><img alt=""></span>
            <span class="sv-meta"><b class="sv-title"></b><small class="sv-ago"></small></span>
            <button type="button" class="sv-btn sv-mute" aria-label="صدا" hidden>🔇</button>
            <button type="button" class="sv-btn sv-close" aria-label="بستن"><?= icon('close', 22) ?></button>
        </div>
        <div class="sv-media"></div>
        <div class="sv-loading"><i></i></div>
        <div class="sv-tap sv-prev" aria-hidden="true"></div>
        <div class="sv-tap sv-next" aria-hidden="true"></div>
        <div class="sv-cap"></div>
        <a class="sv-link" target="_blank" rel="noopener" hidden><span></span> <?= icon('arrow-up', 16) ?></a>
    </div>
    <button type="button" class="sv-side sv-side-prev" aria-label="قبلی"><?= icon('chevron-right', 26) ?></button>
    <button type="button" class="sv-side sv-side-next" aria-label="بعدی"><?= icon('chevron-left', 26) ?></button>
</div>
<script>window.NX_STORIES = <?= json_encode($stories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>

<?php foreach ($sections as $sec): if (empty($sec['on'])) continue; switch ($sec['key']):

case 'hero': if (!$featured) break; ?>
<section class="container hero hero-<?= e($heroStyle) ?>">
    <?php if ($heroStyle === 'slider'): ?>
        <div class="slider glass" id="heroSlider">
            <?php foreach ($featured as $i => $p): ?>
                <div class="slide<?= $i === 0 ? ' active' : '' ?>">
                    <?= cover_html($p, 'slide-img', $i === 0) ?>
                    <div class="slide-content">
                        <span class="slide-chips"><?php if ($p['cat_name']): ?><a class="chip" href="<?= e(url('category/' . rawurlencode($p['cat_slug']))) ?>" style="--c:<?= e($p['cat_color']) ?>"><?= e($p['cat_icon']) ?> <?= e($p['cat_name']) ?></a><?php endif; ?><?php if (!empty($p['is_featured'])): ?> <span class="chip chip-feat">⭐ ویژه</span><?php endif; ?></span>
                        <h2><a href="<?= e(post_url($p)) ?>"><?= e($p['title']) ?></a></h2>
                        <p><?= e($p['excerpt'] ?: str_excerpt((string) $p['content'], 180)) ?></p>
                        <div class="pc-meta"><span><?= icon('calendar', 14) ?> <?= jdate('j F Y', $p['published_at']) ?></span><span><?= icon('eye', 14) ?> <?= fa_short_num((int) $p['views']) ?></span></div>
                        <a class="btn btn-primary" href="<?= e(post_url($p)) ?>">ادامه خبر <?= icon('arrow-left', 16) ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="slider-nav">
                <button class="icon-btn" data-dir="-1" aria-label="قبلی"><?= icon('chevron-right') ?></button>
                <div class="slider-dots"><?php foreach ($featured as $i => $p): ?><button class="<?= $i === 0 ? 'active' : '' ?>" data-go="<?= $i ?>" aria-label="اسلاید <?= $i + 1 ?>"><i></i></button><?php endforeach; ?></div>
                <button class="icon-btn" data-dir="1" aria-label="بعدی"><?= icon('chevron-left') ?></button>
            </div>
        </div>
    <?php elseif ($heroStyle === 'grid'): ?>
        <div class="hero-grid3">
            <?php foreach ($featured as $i => $p) partial('card', ['p' => $p, 'variant' => 'big', 'i' => $i]); ?>
        </div>
    <?php else: ?>
        <div class="hero-mag">
            <div class="hm-main"><?php partial('card', ['p' => $featured[0], 'variant' => 'big', 'i' => 0]); ?></div>
            <div class="hm-side">
                <?php foreach (array_slice($featured, 1, 4) as $i => $p) partial('card', ['p' => $p, 'variant' => 'big', 'i' => $i + 1]); ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<div class="container"><?= ad_slot('home_top') ?></div>
<?php break;

case 'latest':
    $latest = get_posts(['limit' => (int) setting('posts_per_page', 12), 'exclude' => $heroIds]);
    if (!$latest) $latest = get_posts(['limit' => 9]); ?>
<section class="container layout-2col">
    <div class="col-main">
        <div class="sec-head" data-reveal>
            <h2 class="sec-title"><span class="sec-icon"><?= icon('bolt', 20) ?></span> آخرین اخبار</h2>
            <a class="sec-more" href="<?= e(url('archive')) ?>">همه اخبار <?= icon('arrow-left', 16) ?></a>
        </div>
        <?php if ($latest): ?>
            <div class="grid-cards">
                <?php foreach ($latest as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
            </div>
            <div class="center mt"><a class="btn btn-ghost btn-glow" href="<?= e(url('archive')) ?>">مشاهده اخبار بیشتر <?= icon('arrow-left', 16) ?></a></div>
        <?php else: ?>
            <div class="empty glass"><span>📰</span><p>هنوز خبری منتشر نشده است.</p></div>
        <?php endif; ?>
    </div>
    <?php partial('sidebar'); ?>
</section>
<?php break;

case 'categories':
    foreach ($cats as $ci => $c):
        if (!$c['show_on_home'] || !$c['cnt']) continue;
        $cp = get_posts(['category_id' => $c['id'], 'limit' => 6]); ?>
<section class="container cat-block" style="--c:<?= e($c['color']) ?>">
    <div class="sec-head" data-reveal>
        <h2 class="sec-title"><span class="sec-icon emoji"><?= e($c['icon']) ?></span> <?= e($c['name']) ?></h2>
        <div class="sec-tools">
            <button class="icon-btn sm" data-scroll="-1" aria-label="قبلی"><?= icon('chevron-right', 18) ?></button>
            <button class="icon-btn sm" data-scroll="1" aria-label="بعدی"><?= icon('chevron-left', 18) ?></button>
            <a class="sec-more" href="<?= e(cat_url($c)) ?>">بیشتر <?= icon('arrow-left', 16) ?></a>
        </div>
    </div>
    <div class="h-scroll">
        <?php foreach ($cp as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
    </div>
</section>
<?php if (!$middleAdDone): $middleAdDone = true; ?><div class="container"><?= ad_slot('home_middle') ?></div><?php endif; ?>
<?php endforeach; break;

case 'trending':
    $since = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) setting('trending_days', 30)) . ' days'));
    $tr = get_posts(['order' => 'views', 'limit' => 4, 'since' => $since]);
    if (count($tr) < 4) $tr = get_posts(['order' => 'views', 'limit' => 4]);
    if (!$tr) break; ?>
<section class="container trending">
    <div class="sec-head" data-reveal>
        <h2 class="sec-title"><span class="sec-icon"><?= icon('trend', 20) ?></span> پربازدیدترین‌ها</h2>
    </div>
    <div class="trend-grid">
        <?php foreach ($tr as $i => $p): ?>
            <a class="trend-item glass tilt" href="<?= e(post_url($p)) ?>" data-reveal style="--d:<?= $i ?>">
                <span class="trend-num"><?= fa_num((string) ($i + 1)) ?></span>
                <span class="trend-media"><?= cover_html($p) ?></span>
                <span class="trend-body">
                    <b><?= e($p['title']) ?></b>
                    <small><?= icon('eye', 13) ?> <?= fa_short_num((int) $p['views']) ?> بازدید · <?= jago($p['published_at']) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php break;

case 'telegram': if (!$tgLink) break; ?>
<section class="container">
    <div class="tg-cta glass" data-reveal>
        <div class="tg-cta-bg" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="tg-cta-logo"><img src="<?= e(media_url((string) setting('logo'))) ?>" alt="" width="120" height="120"><span class="pulse-ring"></span><span class="pulse-ring d2"></span></div>
        <div class="tg-cta-text">
            <span class="eyebrow"><?= icon('telegram', 16) ?> کانال رسمی تلگرام</span>
            <h2>به کامیونیتی <span class="grad-text">NEXUS</span> بپیوند 💬</h2>
            <p>محتوای روزانه، کاربردی و متفاوت 🔥 — گیم 🎮 | تکنولوژی 💻 | ادیت 🎬 | موزیک 🎵 | سرگرمی 🎉 | اخبار روز 📰</p>
            <div class="tg-cta-actions">
                <a class="btn btn-primary btn-lg" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 20) ?> عضویت در <?= e(telegram_handle()) ?></a>
                <?php if ($g = safe_link((string) setting('social_group'))): ?><a class="btn btn-ghost btn-lg" href="<?= e($g) ?>" target="_blank" rel="noopener"><?= icon('comment', 20) ?> گروه گفتگو</a><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php break;

case 'shop':
    if (!shop_on()) break;
    $shopItems = shop_products(['featured' => 1, 'limit' => 4]);
    if (count($shopItems) < 4) $shopItems = array_merge($shopItems, array_filter(shop_products(['limit' => 8]), fn($x) => !$x['is_featured']));
    $shopItems = array_slice($shopItems, 0, 4);
    if (!$shopItems && !setting_on('shop_banner_on')) break; ?>
<section class="container home-shop">
    <?php if (setting_on('shop_banner_on')): ?><div class="ad-slot shop-promo" data-reveal><?= render_ad(shop_banner_ad(), true) ?></div><?php endif; ?>
    <?php if ($shopItems): ?>
    <div class="sec-head" data-reveal>
        <h2 class="sec-title"><span class="sec-icon"><?= icon('bag', 20) ?></span> <?= e(setting('shop_title')) ?></h2>
        <a class="sec-more" href="<?= e(url('shop')) ?>">همه محصولات <?= icon('arrow-left', 16) ?></a>
    </div>
    <div class="shop-grid">
        <?php foreach ($shopItems as $i => $p) partial('product-card', ['p' => $p, 'i' => $i]); ?>
    </div>
    <?php endif; ?>
</section>
<?php break;

endswitch; endforeach; ?>
