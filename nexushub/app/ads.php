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
    $ads = active_ads($position);
    if (!$ads) return '';
    $mobile = is_mobile_ua();
    $ads = array_values(array_filter($ads, fn($a) => $a['device'] === 'all' || ($a['device'] === 'mobile') === $mobile));
    if (!$ads) return '';

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
        return '<div class="ad-popup" data-id="' . (int) $a['id'] . '" data-delay="' . (int) $a['popup_delay'] . '" data-freq="' . (int) $a['popup_freq'] . '" hidden>'
            . '<div class="ad-popup-backdrop" data-close></div><div class="ad-popup-box"><button class="ad-close" data-close aria-label="بستن">' . icon('close', 18) . '</button>' . $inner . '</div></div>';
    }
    if ($position === 'floating') {
        $a = $ads[0];
        return '<div class="ad-floating" data-id="' . (int) $a['id'] . '" hidden><button class="ad-close" data-close aria-label="بستن">' . icon('close', 16) . '</button>' . $inner . '</div>';
    }
    $stack = in_array($position, ['sidebar'], true);
    $rotate = !$stack && count($ads) > 1;
    return '<div class="ad-slot ad-pos-' . e($position) . ($rotate ? ' ad-rotate' : '') . '" data-reveal>' . $inner . '</div>';
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
    $label = (string) setting('ad_label', 'تبلیغ');

    $deco = match ($theme) {
        'fire' => '<span class="ad-embers" aria-hidden="true">' . str_repeat('<i></i>', 10) . '</span>',
        'aurora' => '<span class="ad-aurora" aria-hidden="true"><i></i><i></i><i></i></span>',
        'glitch' => '<span class="ad-scan" aria-hidden="true"></span>',
        'holo' => '<span class="ad-holo" aria-hidden="true"></span>',
        'glass' => '<span class="ad-shine" aria-hidden="true"></span>',
        'spotlight' => '<span class="ad-spot" aria-hidden="true"></span>',
        default => '',
    };
    $ribbon = ($theme === 'ribbon' || !empty($a['badge'])) ? '<span class="ad-ribbon">' . e($a['badge'] ?: 'ویژه') . '</span>' : '';
    $device = $a['device'] ?? 'all';
    $cls = 'ad ad-theme-' . $theme . ' ad-fmt-' . $format . ($device !== 'all' ? ' only-' . $device : '');
    $out = '<div class="' . $cls . '" style="--ad:' . $accent . ';--ad-rgb:' . hex_to_rgb($accent) . '" data-ad="' . $id . '">';
    $out .= $deco . $ribbon;
    if ($label !== '') $out .= '<span class="ad-label">' . e($label) . '</span>';

    if ($format === 'html') {
        $out .= '<div class="ad-html">' . ($a['html'] ?? '') . '</div>';
    } elseif ($format === 'image') {
        $img = media_url($a['image'] ?? '');
        $out .= '<a class="ad-inner" href="' . e($href) . '"' . $target . ' rel="sponsored noopener">'
            . ($img ? '<img class="ad-banner" src="' . e($img) . '" alt="' . e($a['heading'] ?: $a['title']) . '" loading="lazy">' : '<span class="ad-noimg">تصویر بنر را انتخاب کنید</span>')
            . '</a>';
    } else {
        $img = media_url($a['image'] ?? '');
        $heading = $a['heading'] ?: $a['title'];
        $out .= '<a class="ad-inner" href="' . e($href) . '"' . $target . ' rel="sponsored noopener">';
        if ($img) $out .= '<span class="ad-thumb"><img src="' . e($img) . '" alt="" loading="lazy"></span>';
        $out .= '<span class="ad-content">';
        $out .= '<span class="ad-head">' . (!empty($a['emoji']) ? '<span class="ad-emoji">' . e($a['emoji']) . '</span>' : '')
            . '<strong class="ad-title"' . ($theme === 'glitch' ? ' data-text="' . e($heading) . '"' : '') . '>' . e($heading) . '</strong></span>';
        if (!empty($a['body'])) $out .= '<span class="ad-text">' . nl2br(e($a['body'])) . '</span>';
        if (!empty($a['countdown']) && !empty($a['end_at'])) {
            $out .= '<span class="ad-countdown" data-end="' . e(date('c', strtotime($a['end_at']))) . '">'
                . '<b data-u="d">۰</b><small>روز</small><b data-u="h">۰</b><small>ساعت</small><b data-u="m">۰</b><small>دقیقه</small><b data-u="s">۰</b><small>ثانیه</small></span>';
        }
        $out .= '</span>';
        if (!empty($a['button_text'])) $out .= '<span class="ad-btn">' . e($a['button_text']) . ' ' . icon('arrow-left', 16) . '</span>';
        $out .= '</a>';
    }
    return $out . '</div>';
}
