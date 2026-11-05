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
            foreach ($list as $a) $all[$a['position']][] = $a;
        } catch (Throwable $e) {
        }
    }
    return $all[$position] ?? [];
}

/** خروجی HTML جایگاه تبلیغ */
function ad_slot(string $position): string
{
    $shop = shop_banner_slot($position) . raffle_slot($position);   // the shop banner and running raffles can sit in any of these places too
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

    $deco = match ($theme) {
        'fire' => '<span class="nxp-embers" aria-hidden="true">' . str_repeat('<i></i>', 10) . '</span>',
        'aurora' => '<span class="nxp-aurora" aria-hidden="true"><i></i><i></i><i></i></span>',
        'glitch' => '<span class="nxp-scan" aria-hidden="true"></span>',
        'holo' => '<span class="nxp-holo" aria-hidden="true"></span>',
        'glass' => '<span class="nxp-shine" aria-hidden="true"></span>',
        'spotlight' => '<span class="nxp-spot" aria-hidden="true"></span>',
        default => '',
    };
    $ribbon = ($theme === 'ribbon' || !empty($a['badge'])) ? '<span class="nxp-ribbon">' . e($a['badge'] ?: 'ویژه') . '</span>' : '';
    $device = $a['device'] ?? 'all';
    $cls = 'nxp nxp-theme-' . $theme . ' nxp-fmt-' . $format . ($device !== 'all' ? ' only-' . $device : '');
    $out = '<div class="' . $cls . '" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . '" data-nxp="' . $id . '">';
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
    return ['home_shop' => 'صفحه اصلی — بخش فروشگاه (بالای محصولات ویژه)', 'home_top' => 'صفحه اصلی — زیر اسلایدر', 'home_middle' => 'صفحه اصلی — وسط', 'sidebar' => 'سایدبار', 'post_top' => 'خبر — بالای متن',
        'post_bottom' => 'خبر — پایان متن', 'footer_top' => 'بالای فوتر (همه صفحات)', 'header_top' => 'بالای هدر (همه صفحات)'];
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
    return '<div class="nxp-slot nxp-pos-' . e($position) . ' shop-promo" data-reveal>' . render_ad(shop_banner_ad(), true) . '</div>';
}
