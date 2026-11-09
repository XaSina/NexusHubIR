<?php
/**
 * سیستم تبلیغات
 */

function active_ads(string $position): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        try {
            $n = now();
            $list = rows('SELECT * FROM ads WHERE is_active = 1 AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?) ORDER BY priority DESC, id DESC', [$n, $n]);
            foreach ($list as $a) foreach (ad_places_of($a) as $p) $all[$p][] = $a + ['_slot' => $p];
        } catch (Throwable $e) {
        }
    }
    return $all[$position] ?? [];
}

/** every place an ad is shown in */
function ad_places_of(array $a): array
{
    $l = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($a['places'] ?? ''))))));
    if (!$l && !empty($a['position'])) $l = [(string) $a['position']];
    return array_values(array_intersect($l, array_keys(ad_positions())));
}

/** خروجی HTML جایگاه تبلیغ */
/**
 * one slim line for the very top of the page (above the header): fixed height, one line of text, a small action and ✕.
 * A closed line stays closed for that visitor until its text changes.
 */
function top_strip(string $key, string $icon, string $text, string $href = '', string $btn = '', string $accent = '', string $copy = '', bool $newTab = false, string $img = ''): string
{
    $accent = valid_hex($accent, '') ?: valid_hex((string) setting('color_primary', '#ff1f3d'), '#ff1f3d');
    $text = trim(preg_replace('~\s+~u', ' ', strip_tags($text)));
    $key = $key . '-' . substr(md5($text . $href . $copy), 0, 8);
    $t = $newTab ? ' target="_blank" rel="nofollow noopener"' : '';
    $body = ($img ? '<img class="nxs-img" src="' . e($img) . '" alt="" loading="lazy">' : '<span class="nxs-ic" aria-hidden="true">' . e($icon) . '</span>')
        . '<span class="nxs-tx" title="' . e($text) . '">' . e($text) . '</span>';
    $main = $href ? '<a class="nxs-main" href="' . e($href) . '"' . $t . '>' . $body . '</a>' : '<span class="nxs-main">' . $body . '</span>';
    $act = $copy !== '' ? '<button type="button" class="nxs-btn nxs-copy" data-copy="' . e($copy) . '" data-copy-msg="کد تخفیف کپی شد ✅"><code dir="ltr">' . e($copy) . '</code> 📋</button>'
        : ($href && $btn !== '' ? '<a class="nxs-btn" href="' . e($href) . '"' . $t . '>' . e($btn) . '</a>' : '');
    [$r, $g, $b] = array_map('intval', explode(',', hex_to_rgb($accent)));
    $on = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 165 ? '#0b0d12' : '#fff';          // readable text on the accent button
    return '<div class="nx-strip" data-strip="' . e($key) . '" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . ';--nxp-on:' . $on . '">' . $main . $act
        . '<button type="button" class="nxs-x" data-strip-close aria-label="بستن" title="بستن">' . icon('close', 14) . '</button></div>';
}

function ad_slot(string $position): string
{
    if ($position === 'header_top') return top_strips();
    $html = ad_slot_inner($position);
    // wide places: several boxes sit side by side (two per row) instead of a stack of long empty bars
    if ($html !== '' && !in_array($position, ['sidebar', 'popup', 'floating'], true))
        return '<div class="nxp-row nxp-row-' . e($position) . (setting('wide_single', 'half') === 'full' ? '' : ' nxp-half') . '">' . $html . '</div>';
    return $html;
}

function ad_slot_inner(string $position): string
{
    $shop = shop_banner_slot($position) . raffle_slot($position) . wheel_slot($position) . promo_slot($position);   // the shop banner and running raffles can sit in any of these places too
    $ads = active_ads($position);
    if (!$ads) return $shop;
    $mobile = is_mobile_ua();
    $ads = array_values(array_filter($ads, fn($a) => $a['device'] === 'all' || ($a['device'] === 'mobile') === $mobile));
    if (!$ads) return $shop;

    if ($position === 'popup') $ads = [$ads[0]];
    if ($position === 'floating') $ads = [$ads[0]];

    $ids = array_map(fn($a) => (int) $a['id'], $ads);
    try {
        if (!current_user()) q('UPDATE ads SET impressions = impressions + 1 WHERE id IN (' . implode(',', $ids) . ')'); // panel users aren't counted
    } catch (Throwable $e) {
    }

    $inner = '';
    foreach ($ads as $a) $inner .= render_ad($a);

    if ($position === 'popup') {
        $a = $ads[0];
        return '<div class="nxp-popup" data-id="' . (int) $a['id'] . '" data-delay="' . (int) $a['popup_delay'] . '" data-freq="' . (int) $a['popup_freq'] . '" hidden>'
            . '<div class="nxp-popup-backdrop" data-close></div><div class="nxp-popup-box"><button class="nxp-close" data-close aria-label="بستن">' . icon('close', 18) . '</button>' . $inner . '</div></div>';
    }
    if ($position === 'floating') {
        $a = $ads[0];
        return '<div class="nxp-floating" data-id="' . (int) $a['id'] . '" hidden><button class="nxp-close" data-close aria-label="بستن">' . icon('close', 16) . '</button>' . $inner . '</div>';
    }
    $stack = in_array($position, ['sidebar'], true);
    $rotate = !$stack && count($ads) > 1;
    return $shop . '<div class="nxp-slot nxp-pos-' . e($position) . ($rotate ? ' nxp-rotate' : '') . '" data-reveal>' . $inner . '</div>';
}

/** everything placed «above the header» as slim closable lines (one shows at a time; several take turns) */
function top_strips(): string
{
    $out = shop_banner_slot('header_top') . raffle_slot('header_top') . wheel_slot('header_top') . promo_slot('header_top');
    $ads = array_values(array_filter(active_ads('header_top'), fn($a) => $a['device'] === 'all' || ($a['device'] === 'mobile') === is_mobile_ua()));
    if ($ads) {
        try { if (!current_user()) q('UPDATE ads SET impressions = impressions + 1 WHERE id IN (' . implode(',', array_map(fn($a) => (int) $a['id'], $ads)) . ')'); } catch (Throwable $e) {}
        foreach ($ads as $a) {
            $text = trim(($a['heading'] ?: $a['title']) . ($a['body'] ? ' — ' . $a['body'] : ''));
            if ($a['format'] === 'html' && $text === '') continue;                 // custom code can't be squeezed into one line
            $out .= top_strip('a' . $a['id'], $a['emoji'] ?: '📣', $text ?: 'تبلیغ', safe_link((string) $a['link']) ? url('go/' . (int) $a['id']) : '', $a['button_text'] ?: (safe_link((string) $a['link']) ? 'مشاهده' : ''),
                (string) $a['accent'], '', !empty($a['new_tab']), $a['format'] === 'image' && $a['image'] ? media_url($a['image']) : '');
        }
    }
    if ($out === '') return '';
    // closed lines are removed before they are drawn, so nothing jumps
    return '<div class="nx-strips" id="nxStrips">' . $out . '</div>'
        . '<script>(function(){try{var x=JSON.parse(localStorage.getItem("nx_strip_x")||"{}"),w=document.getElementById("nxStrips"),l=w.querySelectorAll(".nx-strip"),n=0;'
        . 'for(var i=0;i<l.length;i++){if(x[l[i].getAttribute("data-strip")])l[i].remove();else n++;}if(!n)w.remove();else{w.className+=" js";w.querySelector(".nx-strip").classList.add("on");}}catch(e){}})();</script>';
}

/** readable text colour on top of an accent (dark on light accents, white on dark ones) */
function nxp_on(string $hex): string
{
    [$r, $g, $b] = array_map('intval', explode(',', hex_to_rgb($hex)));
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 165 ? '#0b0d12' : '#fff';
}

/** the moving layers a theme draws behind an ad (embers, aurora, scan line …) */
function ad_deco(string $theme): string
{
    return match ($theme) {
        'fire' => '<span class="nxp-embers" aria-hidden="true">' . str_repeat('<i></i>', 10) . '</span>',
        'aurora' => '<span class="nxp-aurora" aria-hidden="true"><i></i><i></i><i></i></span>',
        'glitch' => '<span class="nxp-scan" aria-hidden="true"></span>',
        'holo' => '<span class="nxp-holo" aria-hidden="true"></span>',
        'glass' => '<span class="nxp-shine" aria-hidden="true"></span>',
        'spotlight' => '<span class="nxp-spot" aria-hidden="true"></span>',
        default => '',
    };
}

function render_ad(array $a, bool $preview = false): string
{
    $a += ['id' => 0, 'title' => '', 'format' => 'card', 'theme' => 'neon', 'accent' => '', 'image' => '', 'link' => '', 'html' => '', 'heading' => '',
        'body' => '', 'button_text' => '', 'emoji' => '', 'badge' => '', 'device' => 'all', 'countdown' => 0, 'new_tab' => 1, 'end_at' => null];
    $theme = array_key_exists($a['theme'] ?? '', ad_themes()) ? $a['theme'] : 'neon';
    $format = in_array($a['format'] ?? '', ['card', 'image', 'html'], true) ? $a['format'] : 'card';
    $accent = valid_hex((string) ($a['accent'] ?? ''), '#ff1f3d');
    $id = (int) ($a['id'] ?? 0);
    $link = safe_link($a['link'] ?? '');
    $href = $preview || !$id ? ($link ?: '#') : url('go/' . $id);
    $target = !empty($a['new_tab']) ? ' target="_blank"' : '';
    $label = !empty($a['no_label']) ? '' : (string) setting('ad_label', 'تبلیغ');

    $deco = ad_deco($theme);
    $ribbon = ($theme === 'ribbon' || !empty($a['badge'])) ? '<span class="nxp-ribbon">' . e($a['badge'] ?: 'ویژه') . '</span>' : '';
    $device = $a['device'] ?? 'all';
    $cls = 'nxp nxp-theme-' . $theme . ' nxp-fmt-' . $format . ($device !== 'all' ? ' only-' . $device : '');
    $out = '<div class="' . $cls . '" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . ';--nxp-on:' . nxp_on($accent) . '" data-nxp="' . $id . '">';
    $out .= $deco . $ribbon;
    if ($label !== '') $out .= '<span class="nxp-label">' . e($label) . '</span>';

    if ($format === 'html') {
        $out .= '<div class="nxp-html">' . ($a['html'] ?? '') . '</div>';
    } elseif ($format === 'image') {
        $img = media_url($a['image'] ?? '');
        $out .= '<a class="nxp-inner" href="' . e($href) . '"' . $target . ' rel="nofollow noopener">'
            . ($img ? '<img class="nxp-banner" src="' . e($img) . '" alt="' . e($a['heading'] ?: $a['title']) . '" loading="lazy">' : '<span class="nxp-noimg">تصویر بنر را انتخاب کنید</span>')
            . '</a>';
    } else {
        $img = media_url($a['image'] ?? '');
        $heading = $a['heading'] ?: $a['title'];
        $out .= '<a class="nxp-inner" href="' . e($href) . '"' . $target . ' rel="nofollow noopener">';
        if ($img) $out .= '<span class="nxp-thumb"><img src="' . e($img) . '" alt="" loading="lazy"></span>';
        $out .= '<span class="nxp-content">';
        $out .= '<span class="nxp-head">' . (!empty($a['emoji']) ? '<span class="nxp-emoji">' . e($a['emoji']) . '</span>' : '')
            . '<strong class="nxp-title"' . ($theme === 'glitch' ? ' data-text="' . e($heading) . '"' : '') . '>' . e($heading) . '</strong></span>';
        if (!empty($a['body'])) $out .= '<span class="nxp-text">' . nl2br(e($a['body'])) . '</span>';
        if (!empty($a['countdown']) && !empty($a['end_at'])) {
            $out .= '<span class="nxp-countdown" data-end="' . e(date('c', strtotime($a['end_at']))) . '">'
                . '<b data-u="d">۰</b><small>روز</small><b data-u="h">۰</b><small>ساعت</small><b data-u="m">۰</b><small>دقیقه</small><b data-u="s">۰</b><small>ثانیه</small></span>';
        }
        $out .= '</span>';
        if (!empty($a['button_text'])) $out .= '<span class="nxp-btn">' . e($a['button_text']) . ' ' . icon('arrow-left', 16) . '</span>';
        $out .= '</a>';
    }
    return $out . '</div>';
}

/** Places the shop banner may use (ad slots) */
function shop_banner_places(): array
{
    return ['home_shop' => 'صفحه اصلی — بخش فروشگاه (بالای محصولات ویژه)', 'home_top' => 'صفحه اصلی — زیر اسلایدر', 'home_middle' => 'صفحه اصلی — وسط (بعد از اولین بلوک دسته)', 'sidebar' => 'سایدبار', 'post_top' => 'خبر — بالای متن',
        'post_bottom' => 'خبر — پایان متن', 'footer_top' => 'بالای فوتر (همه صفحات)', 'header_top' => 'بالای هدر — نوار باریک یک‌خطی (همه صفحات)'];
}

function shop_banner_ad(): array
{
    return [
        'id' => 0, 'no_label' => true, 'format' => 'card', 'theme' => (string) setting('shop_banner_style', 'neon'),
        'accent' => (string) setting('shop_banner_accent') ?: (string) setting('color_primary', '#ff1f3d'),
        'image' => (string) setting('shop_banner_image'), 'link' => url('shop'), 'new_tab' => 0,
        'heading' => (string) setting('shop_banner_title'), 'body' => (string) setting('shop_banner_text'),
        'button_text' => (string) setting('shop_banner_btn'), 'emoji' => (string) setting('shop_banner_emoji', '🛍️'),
        'badge' => (string) setting('shop_banner_badge'),
    ];
}

function shop_banner_slot(string $position): string
{
    static $places = null;
    if (!shop_on() || !setting_on('shop_banner_on') || !isset(shop_banner_places()[$position])) return '';
    $places ??= array_filter(array_map('trim', explode(',', (string) setting('shop_banner_places'))));
    if (!in_array($position, $places, true)) return '';
    if ($position === 'header_top') { $a = shop_banner_ad(); return top_strip('s', $a['emoji'] ?: '🛍️', trim($a['heading'] . ($a['body'] ? ' — ' . $a['body'] : '')) ?: 'فروشگاه', url('shop'), $a['button_text'] ?: 'بریم فروشگاه', (string) $a['accent']); }
    return '<div class="nxp-slot nxp-pos-' . e($position) . ' shop-promo" data-reveal>' . render_ad(shop_banner_ad(), true) . '</div>';
}
