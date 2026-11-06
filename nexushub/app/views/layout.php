<?php
/** @var string $content @var array $meta @var string $bodyClass */
$siteTitle = (string) setting('site_title');
$title = $meta['title'] ?? $siteTitle;
$desc = $meta['description'] ?? setting('site_description');
$image = $meta['image'] ?? '';
if ($image === '' && setting('og_image') !== '') $image = site_url((string) setting('og_image'));
$ogLogo = in_array((string) setting('logo'), ['', 'assets/img/logo-256.png'], true) ? 'assets/img/logo.jpg' : (string) setting('logo');
if ($image === '') $image = site_url($ogLogo);
$colors = theme_colors();
$bgEffect = (string) setting('bg_effect', 'particles');
$cardStyle = (string) setting('card_style', 'glass');
$jsCfg = [
    'base' => nx_base_path(),
    'csrf' => csrf_token(),
    'primary' => $colors['primary'],
    'secondary' => $colors['secondary'],
    'bg' => $bgEffect,
    'tilt' => setting_on('anim_tilt'),
    'cursor' => setting_on('anim_cursor'),
    'reveal' => setting_on('anim_reveal'),
    'light' => $colors['light'],
];
$menu = setting_json('menu');
$reqPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$ticker = setting_on('show_ticker') ? get_posts(['breaking' => 1, 'limit' => 10]) : [];
$pages = rows("SELECT title, slug, show_in_menu, show_in_footer FROM pages WHERE status = 'published' ORDER BY sort_order, id");
$cats = get_categories();
$tgLink = safe_link((string) setting('social_telegram'));
$me = current_member();
// The per-category switches are authoritative everywhere: a manual menu link that points to a
// category is shown only if that category's switch is on (before, manual links ignored the switch).
$catBySlug = [];
foreach ($cats as $c) $catBySlug[mb_strtolower($c['slug'])] = $c;
$menuCat = function (array $m) use ($catBySlug): ?array {
    $u = rawurldecode((string) ($m['url'] ?? ''));
    if (preg_match('~(?:^|/)category/([^/?#]+)/?$~u', (string) parse_url($u, PHP_URL_PATH), $mm)) return $catBySlug[mb_strtolower($mm[1])] ?? null;
    return null;
};
$navItems = [];      // desktop header  (switch: show_in_header)
$drawerItems = [];   // mobile ☰ menu   (switch: show_in_mobile)
$navCatIds = []; $drawerCatIds = []; $drawerAt = null;
foreach ($menu as $m) {
    $c = $menuCat($m);
    if ($c) {
        $id = (int) $c['id'];
        if (!empty($c['show_in_header']) && !in_array($id, $navCatIds, true)) { $navItems[] = $m; $navCatIds[] = $id; }
        if (!empty($c['show_in_mobile']) && !in_array($id, $drawerCatIds, true)) { $drawerItems[] = $m + ['cat' => $c]; $drawerCatIds[] = $id; }
        $drawerAt = count($drawerItems);   // other switched-on categories go right after the menu's own categories
        continue;
    }
    $navItems[] = $m;
    $drawerItems[] = $m;
    if ($drawerAt === null && preg_match('~(?:^|/)archive/?$~', (string) ($m['url'] ?? ''))) $drawerAt = count($drawerItems);
}
$extraDrawer = [];
foreach ($cats as $c) {   // switched-on categories that aren't in the manual menu
    $item = ['title' => $c['name'], 'url' => '/category/' . $c['slug'], 'cat' => $c];
    if (!empty($c['show_in_header']) && !in_array((int) $c['id'], $navCatIds, true)) $navItems[] = $item;
    if (!empty($c['show_in_mobile']) && !in_array((int) $c['id'], $drawerCatIds, true)) $extraDrawer[] = $item;
}
array_splice($drawerItems, $drawerAt ?? count($drawerItems), 0, $extraDrawer);
// shop: menu places are chosen in the panel (desktop header / mobile menu / footer)
$shopOn = shop_on();
if ($shopOn && setting_on('shop_in_header')) $navItems[] = shop_menu_item();
if ($shopOn && setting_on('shop_in_mobile')) array_splice($drawerItems, min(1, count($drawerItems)), 0, [shop_menu_item()]);
$cartN = $shopOn ? cart_count() : 0;
$jsCfg['member'] = (bool) $me;
$jsCfg['cart'] = $cartN;
?><!doctype html>
<html lang="fa" dir="rtl" class="<?= $colors['light'] ? 'theme-light' : 'theme-dark' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(str_excerpt((string) $desc, 300)) ?>">
<?php if (setting('meta_keywords')): ?><meta name="keywords" content="<?= e(setting('meta_keywords')) ?>"><?php endif; ?>
<?php if (!empty($meta['noindex'])): ?><meta name="robots" content="noindex,follow"><?php else: ?><meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1"><?php endif; ?>
<meta name="theme-color" content="<?= e($colors['bg']) ?>">
<?php if (setting('google_verify') !== ''): ?><meta name="google-site-verification" content="<?= e(setting('google_verify')) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($siteTitle) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e(str_excerpt((string) $desc, 300)) ?>">
<meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
<meta property="og:image" content="<?= e($image) ?>">
<?php if (!empty($meta['url'])): ?><meta property="og:url" content="<?= e($meta['url']) ?>"><link rel="canonical" href="<?= e($meta['url']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?= seo_meta_tags($meta, $title, (string) $desc, $image) ?>
<link rel="icon" href="<?= e(media_url((string) setting('favicon'))) ?>">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/icon-192.png')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/Vazirmatn-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/boxes.css')) ?>">
<?php if ($shopOn || $me): ?><link rel="stylesheet" href="<?= e(asset('css/shop.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/warp.css')) ?>">
<?php $decorHtml = decor_html('site'); if ($decorHtml): ?><link rel="stylesheet" href="<?= e(asset('css/decor.css')) ?>"><?php endif; ?>
<style><?= theme_css_vars() ?></style>
<?= perf_head() ?>
<?php if (setting('custom_css')): ?><style><?= str_ireplace('</style', '', (string) setting('custom_css')) ?></style><?php endif; ?>
<?php if (empty($meta['noindex'])) echo seo_jsonld($meta, $title, (string) $desc, $image, site_url($ogLogo)); ?>
<?= setting('head_code') ?>
</head>
<body<?= setting_on('perf_widget_on') ? '' : ' data-no-perf' ?> class="<?= e($bodyClass) ?> cards-<?= e($cardStyle) ?> hero-<?= e(setting('hero_style', 'magazine')) ?><?= setting_on('anim_noise') ? ' has-noise' : '' ?><?= setting_on('anim_glitch') ? ' has-glitch' : '' ?>">
<?php if (setting_on('maintenance') && current_user()): ?>
<div class="mnt-bar" role="status">🔧 <b>حالت تعمیر روشن است</b> — بازدیدکنندگان صفحه‌ی تعمیر را می‌بینند؛ فقط شما (مدیر) سایت را می‌بینید. <a href="?maintenance_preview=1">دیدن صفحه‌ی تعمیر</a> · <a href="<?= e(url('admin/settings.php?tab=advanced')) ?>">خاموش کردن</a></div>
<?php endif; ?>
<link rel="preload" href="<?= e(logo_url()) ?>" as="image">
<script>try{var w=+sessionStorage.getItem('nx_warp');if(w){var d=document.documentElement,el=Date.now()-w;d.classList.add('warp-in');if(w>1&&el>=0&&el<20000)d.style.setProperty('--wt',-el+'ms')}}catch(e){}</script>
<?php if (setting_on('warp_on')): ?><div class="nx-warp" id="nxWarp" aria-hidden="true"><div class="nx-warp-disc"></div><div class="nx-warp-fx"><i class="wf-aura"></i><i class="wf-grid"></i><i class="wf-sonar"></i><i class="wf-sonar s2"></i><i class="wf-sonar s3"></i><i class="wf-sweep"></i><i class="wf-scan"></i><i class="wf-corner c1"></i><i class="wf-corner c2"></i><i class="wf-corner c3"></i><i class="wf-corner c4"></i><span class="wf-code">NEXUS://ACCESS · SECURE LINK</span></div><div class="nx-warp-core"><span class="wc-orb"><span class="wc-glow"></span><span class="wc-ring r3"></span><span class="wc-ring r1"><i class="wc-dot"></i></span><span class="wc-ring r2"></span><img src="<?= e(logo_url()) ?>" alt="" onload="this.classList.add('ok')"></span><span class="wc-name">NEXUS<b>|</b>HUB</span><span class="wc-bar"><i></i></span></div></div><?php endif; ?>

<?php if (setting_on('anim_preloader')): ?>
<div class="preloader" id="preloader" aria-hidden="true">
    <div class="pl-core">
        <span class="pl-ring r1"></span><span class="pl-ring r2"></span><span class="pl-ring r3"></span>
        <img src="<?= e(logo_url()) ?>" alt="">
    </div>
    <div class="pl-text glitch" data-text="<?= e($siteTitle) ?>"><?= e($siteTitle) ?></div>
    <div class="pl-bar"><i></i></div>
</div>
<?php endif; ?>

<?= $decorHtml ?>
<div class="bg-layer" aria-hidden="true">
    <div class="bg-blob b1"></div><div class="bg-blob b2"></div><div class="bg-blob b3"></div>
    <?php if ($bgEffect === 'grid'): ?><div class="bg-grid"></div><?php endif; ?>
    <?php if ($bgEffect === 'nebula'): ?><div class="bg-nebula"><i></i><i></i><i></i></div><?php endif; ?>
    <?php if ($bgEffect === 'hex'): ?><div class="bg-hex"></div><?php endif; ?>
    <?php if ($bgEffect === 'aurora'): ?><div class="bg-aurora"></div><?php endif; ?>
    <?php if (in_array($bgEffect, ['particles', 'matrix', 'stars', 'waves', 'bokeh', 'fireflies', 'embers', 'rain'], true)): ?><canvas id="bgCanvas"></canvas><?php endif; ?>
    <div class="bg-vignette"></div>
</div>
<?php if (setting_on('anim_cursor')): ?><div class="cursor-glow" id="cursorGlow" aria-hidden="true"></div><?php endif; ?>
<div class="read-progress" id="readProgress"></div>

<?= ad_slot('header_top') ?>

<?php if (setting_on('announce_on') && setting('announce_text')): $al = safe_link((string) setting('announce_link')); ?>
<div class="announce announce-<?= e(setting('announce_style', 'pinned')) ?>" id="announce" data-hash="<?= e(substr(md5((string) setting('announce_text')), 0, 8)) ?>">
    <div class="container announce-inner">
        <span class="announce-pin"><?= icon('pin', 16) ?> پیام سنجاق‌شده</span>
        <?php if ($al): ?><a href="<?= e($al) ?>" target="_blank" rel="noopener" class="announce-text"><?= e(setting('announce_text')) ?></a>
        <?php else: ?><span class="announce-text"><?= e(setting('announce_text')) ?></span><?php endif; ?>
        <button class="announce-close" aria-label="بستن"><?= icon('close', 14) ?></button>
    </div>
</div>
<?php endif; ?>

<div class="topbar">
    <div class="container topbar-inner">
        <span class="tb-date"><?= icon('calendar', 15) ?> <?= jdate('l j F Y') ?> <b class="tb-clock" id="liveClock"><?= jdate('H:i') ?></b></span>
        <?php if (setting_on('show_online')): [$onl] = online_count(); ?><span class="tb-online" title="کاربران آنلاین در ۵ دقیقه اخیر"><i></i><b id="nxOnline"><?= fa_num((string) max(1, $onl)) ?></b> نفر آنلاین</span><?php endif; ?>
        <span class="tb-social">
            <?php foreach (social_links() as $s): ?>
                <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" title="<?= e($s['label']) ?>"><?= icon($s['icon'], 15) ?></a>
            <?php endforeach; ?>
        </span>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container">
        <div class="header-inner glass">
            <a class="brand" href="<?= e(url('')) ?>">
                <span class="brand-logo"><img src="<?= e(logo_url()) ?>" alt="<?= e($siteTitle) ?>" width="46" height="46"></span>
                <span class="brand-text">
                    <b class="glitch" data-text="<?= e($siteTitle) ?>"><?= e($siteTitle) ?></b>
                    <small><?= e(setting('brand_sub')) ?></small>
                </span>
            </a>
            <nav class="main-nav" aria-label="منوی اصلی">
                <?php foreach ($navItems as $m):
                    $href = menu_link((string) ($m['url'] ?? '/'));
                    $active = rtrim($reqPath, '/') === rtrim(parse_url($href, PHP_URL_PATH) ?: '', '/');
                ?>
                    <a href="<?= e($href) ?>" class="<?= $active || (!empty($m['shop']) && str_contains((string) $bodyClass, 'page-shop')) ? 'active' : '' ?><?= !empty($m['shop']) ? ' nav-shop' : '' ?>"<?= preg_match('~^https?://~', (string) ($m['url'] ?? '')) ? ' target="_blank" rel="noopener"' : '' ?>><?= !empty($m['shop']) ? icon('bag', 16) . ' ' : '' ?><?= e($m['title'] ?? '') ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-actions">
                <button class="icon-btn" id="searchOpen" aria-label="جستجو"><?= icon('search') ?></button>
                <?php if ($shopOn && ($cartN || str_contains((string) $bodyClass, 'page-shop'))): ?><a class="icon-btn cart-btn" id="cartBtn" href="<?= e(url('cart')) ?>" aria-label="سبد خرید"><?= icon('cart') ?><em class="hb-count" id="cartCount"<?= $cartN ? '' : ' hidden' ?>><?= fa_num((string) $cartN) ?></em></a><?php endif; ?>
                <?php if ($me): $nUnread = notifs_unread('m:' . $me['id']); ?>
                <div class="nb-wrap">
                    <button class="icon-btn nb-btn" id="nbBtn" aria-label="اعلان‌ها" aria-expanded="false"><?= icon('bell') ?><em class="hb-count" id="nbCount"<?= $nUnread ? '' : ' hidden' ?>><?= fa_num((string) $nUnread) ?></em></button>
                    <div class="nb-panel" id="nbPanel" hidden>
                        <div class="nb-head"><b><?= icon('bell', 16) ?> اعلان‌ها</b><span class="nb-links"><a href="<?= e(url('notifications')) ?>">همه‌ی اعلان‌ها</a><a href="<?= e(url('prizes')) ?>">🎁 جایزه‌ها</a><?php if ($shopOn): ?><a href="<?= e(url('orders')) ?>">سفارش‌ها</a><?php endif; ?></span></div>
                        <div class="nb-list" id="nbList"><div class="nb-empty"><span>🔔</span>در حال بارگذاری...</div></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($tgLink && setting_on('hdr_tg_on')): ?><a class="btn-tg" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?><span><?= e(setting('hdr_tg_label')) ?></span></a><?php endif; ?>
                <?php if ($me): ?>
                    <a class="btn-account is-in" href="<?= e(url('account')) ?>" data-warp title="حساب کاربری">
                        <span class="ba-avatar"><?= e(mb_substr($me['first_name'], 0, 1)) ?></span><span class="ba-text"><small>حساب کاربری</small><b><?= e($me['first_name']) ?></b></span>
                    </a>
                <?php else: ?>
                    <a class="btn-account" href="<?= e(url('auth')) ?>" data-warp title="ورود / ثبت‌نام"><?= icon('user', 18) ?><span><?= e(setting('hdr_login_label')) ?></span></a>
                <?php endif; ?>
                <button class="icon-btn burger" id="menuOpen" aria-label="منو"><?= icon('menu') ?></button>
            </div>
        </div>
    </div>
</header>

<?php if ($ticker): ?>
<div class="ticker container" style="--speed:<?= max(5, min(300, (int) setting('ticker_speed', 40))) ?>s">
    <div class="ticker-inner glass">
        <span class="ticker-label"><span class="live-dot"></span><?= e(setting('ticker_label', 'فوری')) ?></span>
        <div class="ticker-track">
            <div class="ticker-move">
                <?php foreach ($ticker as $t): ?>
                    <a href="<?= e(post_url($t)) ?>"><?= icon('bolt', 14) ?> <?= e($t['title']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<main id="main" class="site-main">
<?= $content ?>
</main>

<div class="container"><?= ad_slot('footer_top') ?></div>

<footer class="site-footer">
    <div class="footer-glow" aria-hidden="true"></div>
    <div class="container footer-grid">
        <div class="f-about">
            <a class="brand" href="<?= e(url('')) ?>">
                <span class="brand-logo"><img src="<?= e(logo_url()) ?>" alt="" width="46" height="46"></span>
                <span class="brand-text"><b><?= e($siteTitle) ?></b><small><?= e(setting('brand_sub')) ?></small></span>
            </a>
            <p><?= e(setting('footer_text')) ?></p>
            <div class="f-social">
                <?php foreach (social_links() as $s): ?>
                    <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" title="<?= e($s['label']) ?>"><?= icon($s['icon'], 18) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <h4><?= e(setting('footer_cats_title')) ?></h4>
            <ul class="f-links">
                <?php foreach ($cats as $c): if (empty($c['show_in_footer'])) continue; ?><li><a href="<?= e(cat_url($c)) ?>"><?= e($c['icon']) ?> <?= e($c['name']) ?></a></li><?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4><?= e(setting('footer_links_title')) ?></h4>
            <ul class="f-links">
                <li><a href="<?= e(url('archive')) ?>">همه اخبار</a></li>
                <?php if (setting_on('footer_raffle_link')) { try { $rfN = (int) val("SELECT COUNT(*) FROM raffles WHERE status = 'active' OR (status = 'drawn' AND drawn_at >= DATE_SUB(?, INTERVAL result_hours HOUR))", [now()]); } catch (Throwable $e) { $rfN = 0; } } else $rfN = 0; if ($rfN): ?><li><a href="<?= e(url('raffle')) ?>">🎰 قرعه‌کشی</a></li><?php endif; ?>
                <?php if (setting_on('footer_wheel_link')) { try { $lwN = (int) val("SELECT COUNT(*) FROM wheels WHERE audience = 'public' AND status = 'active' AND (end_at IS NULL OR end_at > ?)", [now()]); } catch (Throwable $e) { $lwN = 0; } } else $lwN = 0; if ($lwN): ?><li><a href="<?= e(url('wheel')) ?>">🎡 گردونه شانس</a></li><?php endif; ?>
                <?php if ($shopOn && setting_on('shop_in_footer')): ?><li><a href="<?= e(url('shop')) ?>">🛍️ <?= e(setting('shop_menu_label', 'فروشگاه')) ?></a></li><?php if (setting_on('shop_support_on')): ?><li><a href="<?= e(url('support')) ?>">پشتیبانی فروشگاه</a></li><?php endif; ?><?php endif; ?>
                <?php foreach ($pages as $p): if (!$p['show_in_footer']) continue; ?><li><a href="<?= e(url('page/' . rawurlencode($p['slug']))) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
                <?php if (setting_on('link_prizes_footer')): ?><li><a href="<?= e(url('prizes')) ?>">🎁 جایزه‌های من</a></li><?php endif; ?>
                <?php if (setting_on('link_notifs_footer')): ?><li><a href="<?= e(url('notifications')) ?>">🔔 اعلان‌های من</a></li><?php endif; ?>
                <li><a href="<?= e(url('contact')) ?>">تماس با ما</a></li>
            </ul>
        </div>
        <?php if (setting_on('footer_cta_on')): ?>
        <div class="f-cta glass">
            <span class="f-cta-icon"><?= icon('telegram', 30) ?></span>
            <h4><?= e(setting('footer_cta_title')) ?></h4>
            <p><?= e(setting('footer_cta_text')) ?></p>
            <?php if ($tgLink): ?><a class="btn btn-primary" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> <?= e(setting('footer_cta_btn', 'کانال تلگرام')) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php $tBadges = array_filter(array_map('trust_badge_html', trust_badges())); $tAdmin = can('*'); ?>
    <?php if ($tBadges || $tAdmin): ?>
    <div class="container f-trust"<?= $tAdmin ? ' data-admin' : '' ?>>
        <div class="f-trust-head"><span class="f-trust-ic"><?= icon('shield', 18) ?></span><b><?= e(setting('trust_title', 'نمادهای اعتماد')) ?></b></div>
        <div class="f-trust-list">
            <?php foreach (trust_badges() as $tb): $h = trust_badge_html($tb); if ($h === '') continue; ?><div class="f-trust-item" data-name="<?= e($tb['title'] ?? '') ?>"><?= $h ?></div><?php endforeach; ?>
            <?php if (!$tBadges && $tAdmin): ?><a class="f-trust-item f-trust-empty" href="<?= e(url('admin/appearance.php')) ?>"><?= icon('plus', 20) ?><small>افزودن نماد اعتماد</small></a><a class="f-trust-item f-trust-empty" href="<?= e(url('admin/appearance.php')) ?>"><?= icon('plus', 20) ?><small>افزودن نماد</small></a><?php endif; ?>
        </div>
        <?php if (!$tBadges && $tAdmin): ?><small class="f-trust-note">این جای خالی فقط برای شما (مدیر) نمایش داده می‌شه.</small><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="container f-bottom">
        <span>© <?= jdate('Y') ?> — <?= e(setting('copyright')) ?></span>
        <?php $made = (string) setting('footer_made'); if ($made !== ''): ?><span class="f-made"><?= str_replace('❤', '<span class="heart">❤</span>', e($made)) ?></span><?php endif; ?>
    </div>
</footer>

<div class="search-overlay" id="searchOverlay" hidden>
    <button class="icon-btn so-close" id="searchClose" aria-label="بستن"><?= icon('close') ?></button>
    <form class="so-form" action="<?= e(url('search')) ?>" method="get">
        <span class="so-label">دنبال چی می‌گردی؟</span>
        <div class="so-field glass">
            <?= icon('search', 24) ?>
            <input type="search" name="q" placeholder="مثلاً: GTA VI، آیفون، رزیدنت ایول ..." autocomplete="off" required minlength="2">
            <button class="btn btn-primary" type="submit">جستجو</button>
        </div>
        <div class="so-tags">
            <?php foreach (popular_tags(8) as $t): ?><a href="<?= e(tag_url($t)) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
        </div>
    </form>
</div>

<div class="drawer" id="drawer" aria-hidden="true" inert>
    <div class="drawer-backdrop" data-close></div>
    <aside class="drawer-panel glass">
        <div class="drawer-head">
            <a class="brand" href="<?= e(url('')) ?>"><span class="brand-logo"><img src="<?= e(logo_url()) ?>" alt="" width="40" height="40"></span><span class="brand-text"><b><?= e($siteTitle) ?></b></span></a>
            <button class="icon-btn" data-close aria-label="بستن"><?= icon('close') ?></button>
        </div>
        <nav class="drawer-nav">
            <?php foreach ($drawerItems as $i => $m): $dc = $m['cat'] ?? null; $ds = !empty($m['shop']); ?>
                <a href="<?= e(menu_link((string) ($m['url'] ?? '/'))) ?>" style="--i:<?= $i ?><?= $dc ? ';--c:' . e($dc['color']) : '' ?>"<?= $dc ? ' class="dn-cat"' : ($ds ? ' class="dn-shop"' : '') ?>><span><?php if ($dc): ?><i class="dn-ic"><?= e($dc['icon']) ?></i><?php elseif ($ds): ?><i class="dn-ic"><?= icon('bag', 16) ?></i><?php endif; ?><?= e($m['title'] ?? '') ?><?php if ($ds && $cartN): ?> <em class="dn-badge"><?= fa_num((string) $cartN) ?> در سبد</em><?php endif; ?></span> <?= icon('chevron-left', 16) ?></a>
            <?php endforeach; ?>
            <?php if ($me && setting_on('link_prizes_drawer')): ?><a href="<?= e(url('prizes')) ?>" style="--i:<?= count($drawerItems) ?>"><span><i class="dn-ic">🎁</i>جایزه‌های من</span> <?= icon('chevron-left', 16) ?></a><?php endif; ?>
            <?php if ($me && setting_on('link_notifs_drawer')): ?><a href="<?= e(url('notifications')) ?>" style="--i:<?= count($drawerItems) ?>"><span><i class="dn-ic"><?= icon('bell', 16) ?></i>اعلان‌های من</span> <?= icon('chevron-left', 16) ?></a><?php endif; ?>
            <?php if ($me && $shopOn): ?>
                <a href="<?= e(url('orders')) ?>" style="--i:<?= count($drawerItems) ?>"><span><i class="dn-ic"><?= icon('box', 16) ?></i>سفارش‌های من</span> <?= icon('chevron-left', 16) ?></a>
                <?php if (setting_on('shop_support_on')): ?><a href="<?= e(url('support')) ?>" style="--i:<?= count($drawerItems) + 1 ?>"><span><i class="dn-ic"><?= icon('support', 16) ?></i>پشتیبانی</span> <?= icon('chevron-left', 16) ?></a><?php endif; ?>
            <?php endif; ?>
        </nav>
        <?php if ($me): ?><a class="btn btn-ghost btn-block" href="<?= e(url('account')) ?>" data-warp><?= icon('user', 18) ?> حساب کاربری (<?= e($me['first_name']) ?>)</a>
        <?php else: ?><a class="btn btn-ghost btn-block" href="<?= e(url('auth')) ?>" data-warp><?= icon('user', 18) ?> <?= e(setting('hdr_login_label')) ?></a><?php endif; ?>
        <?php if ($tgLink): ?><a class="btn btn-primary btn-block" href="<?= e($tgLink) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> <?= e(setting('hdr_tg_label')) ?></a><?php endif; ?>
    </aside>
</div>

<?php if (setting_on('to_top_on')): ?><button class="to-top" id="toTop" aria-label="بازگشت به بالا">
    <svg viewBox="0 0 48 48" class="to-top-ring"><circle class="tt-track" cx="24" cy="24" r="21"/><circle class="tt-prog" cx="24" cy="24" r="21"/></svg>
    <?= icon('arrow-up', 20) ?>
</button><?php endif; ?>
<div class="toast-wrap" id="toasts"></div>

<?= ad_slot('popup') ?>
<?= ad_slot('floating') ?>

<script>window.NX = <?= json_encode($jsCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(asset('js/confirm.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if ($shopOn || $me): ?><script src="<?= e(asset('js/shop.js')) ?>" defer></script><?php endif; ?>
<?php if (str_contains((string) ($bodyClass ?? ''), 'page-account')): ?><script src="<?= e(asset('js/pw.js')) ?>" defer></script><?php endif; ?>
<?= setting('footer_code') ?>
<?= perf_foot() ?>
</body>
</html>
