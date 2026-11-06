<?php
/**
 * تنظیمات سایت (key/value در جدول settings)
 */

function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = default_settings();
        try {
            foreach (rows('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = (string) $r['svalue'];
            }
        } catch (Throwable $e) {
            // جدول هنوز ساخته نشده
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_on(string $key): bool
{
    return (string) setting($key, '0') === '1';
}

function setting_json(string $key): array
{
    $v = json_decode((string) setting($key, '[]'), true);
    return is_array($v) ? $v : [];
}

function save_settings(array $data): void
{
    $st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($data as $k => $v) {
        $st->execute([$k, is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v]);
    }
    settings_all(true);
}

function hex_to_rgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    if (!preg_match('~^[0-9a-f]{6}$~i', $hex)) return '255,31,61';
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}

function valid_hex(string $c, string $fallback): string
{
    return preg_match('~^#[0-9a-f]{6}$~i', $c) ? strtolower($c) : $fallback;
}

/** رنگ‌های نهایی تم فعال */
function theme_colors(): array
{
    $presets = theme_presets();
    $key = setting('theme_preset', 'nexus');
    $p = $presets[$key] ?? $presets['nexus'];
    if ($key === 'custom' || $p['primary'] === null) {
        $bg = valid_hex((string) setting('color_bg'), '#050507');
        // تشخیص روشن بودن پس‌زمینه
        [$r, $g, $b] = array_map('intval', explode(',', hex_to_rgb($bg)));
        $light = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150;
        return [
            'primary' => valid_hex((string) setting('color_primary'), '#ff1f3d'),
            'secondary' => valid_hex((string) setting('color_secondary'), '#ff7a3d'),
            'bg' => $bg,
            'light' => $light,
        ];
    }
    return ['primary' => $p['primary'], 'secondary' => $p['secondary'], 'bg' => $p['bg'], 'light' => $p['light']];
}

/** متغیرهای CSS تم برای <head> */
function theme_css_vars(): string
{
    $c = theme_colors();
    $blur = max(0, min(40, (int) setting('glass_blur', 18)));
    $op = max(10, min(95, (int) setting('glass_opacity', 55))) / 100;
    $radius = max(0, min(36, (int) setting('radius', 20)));
    $scale = max(85, min(120, (int) setting('font_scale', 100)));
    $vars = [
        '--primary' => $c['primary'],
        '--primary-rgb' => hex_to_rgb($c['primary']),
        '--secondary' => $c['secondary'],
        '--secondary-rgb' => hex_to_rgb($c['secondary']),
        // text colour on primary-coloured buttons: dark on light themes (silver / white), white otherwise
        '--on-primary' => (function ($h) { [$r, $g, $b] = array_map('intval', explode(',', hex_to_rgb($h))); return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 165 ? '#14151a' : '#fff'; })((string) $c['primary']),
        // background glows: a near-white secondary would show as a white halo, so they use the primary then
        '--glow2-rgb' => (function ($s, $p) { [$r, $g, $b] = array_map('intval', explode(',', hex_to_rgb($s))); return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 170 ? hex_to_rgb($p) : hex_to_rgb($s); })((string) $c['secondary'], (string) $c['primary']),
        // text on primary→secondary gradients: dark when the gradient is light on average
        '--on-grad' => (function ($a, $b2) { $l = fn($h) => (function ($v) { [$r, $g, $b] = array_map('intval', explode(',', $v)); return 0.299 * $r + 0.587 * $g + 0.114 * $b; })(hex_to_rgb($h)); return ($l($a) + $l($b2)) / 2 > 150 ? '#14151a' : '#fff'; })((string) $c['primary'], (string) $c['secondary']),
        '--bg' => $c['bg'],
        '--bg-rgb' => hex_to_rgb($c['bg']),
        '--blur' => $blur . 'px',
        '--glass-a' => (string) $op,
        '--radius' => $radius . 'px',
        '--fs' => ($scale / 100 * 16) . 'px',
    ];
    if ($c['light']) {
        $vars += [
            '--text' => '#12131a', '--text-rgb' => '18,19,26', '--muted' => '#5b5e6e',
            '--surface-rgb' => '255,255,255', '--line' => 'rgba(18,19,26,.10)', '--shadow' => '0 0 0 0 transparent',
        ];
    } else {
        $vars += [
            '--text' => '#f4f4f7', '--text-rgb' => '244,244,247', '--muted' => '#9d9dab',
            '--surface-rgb' => '18,18,24', '--line' => 'rgba(255,255,255,.08)', '--shadow' => '0 0 0 0 transparent',
        ];
    }
    $css = ':root{';
    foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
    return $css . '}';
}

/** The occasion effect in use: setting theme_decor = auto (follow the theme) | off | a decor_types() key */
function active_decor(): ?string
{
    $d = (string) setting('theme_decor', 'auto');
    if ($d === 'off') return null;
    if ($d === 'auto') {
        $p = theme_presets()[(string) setting('theme_preset', 'nexus')] ?? null;
        $d = (string) ($p['decor'] ?? '');
    }
    return isset(decor_types()[$d]) ? $d : null;
}

/**
 * Falling / rising / twinkling particles for occasion themes (snow, petals, hearts, bats, embers...).
 * Pure CSS (transform + opacity), fixed overlay, never catches clicks. $area: site | auth | admin
 */
function decor_html(string $area = 'site'): string
{
    $type = active_decor();
    if (!$type || ($area === 'admin' && !setting_on('decor_admin'))) return '';
    $n = max(6, min(40, (int) setting('decor_density', 18)));
    if ($area === 'admin') $n = (int) ceil($n * .6);
    $glyphs = [
        'snow' => [''], 'petals' => ['🌸', '🌸', '💮'], 'hearts' => ['💖', '❤️', '💕', '💗'], 'halloween' => ['🦇', '🦇', '🎃', '🕸️'],
        'leaves' => ['🍁', '🍂', '🍁'], 'leaves_green' => ['🍃', '🌿', '🍀'], 'yalda' => ['🍉', '', '', '🔴'], 'embers' => [''],
        'ramadan' => ['✦', '✦', '🌙', '✧'], 'sparkles' => ['✦', '✧', '✨'],
    ][$type];
    $mode = match ($type) { 'embers', 'hearts' => 'rise', 'ramadan', 'sparkles' => 'twinkle', 'halloween' => 'fly', default => 'fall' };
    mt_srand(crc32($type . $area));
    $h = '<div class="nx-decor decor-' . e($type) . ' dm-' . $mode . ' da-' . e($area) . '" aria-hidden="true">';
    for ($i = 0; $i < $n; $i++) {
        $g = $glyphs[$i % count($glyphs)];
        $x = mt_rand(0, 1000) / 10;
        $y = mt_rand(0, 900) / 10;
        $size = $type === 'snow' || $type === 'embers' || $g === '' ? mt_rand(4, 11) : mt_rand(14, 30);
        $dur = $mode === 'twinkle' ? mt_rand(25, 60) / 10 : ($mode === 'fly' ? mt_rand(140, 260) / 10 : mt_rand(90, 200) / 10);
        $delay = -mt_rand(0, (int) ($dur * 10)) / 10;
        $sway = mt_rand(20, 90) * (mt_rand(0, 1) ? 1 : -1);
        $h .= '<span style="--x:' . $x . '%;--y:' . $y . '%;--s:' . $size . 'px;--t:' . $dur . 's;--d:' . $delay . 's;--w:' . $sway . 'px"><i>' . ($g === '' ? '' : e($g)) . '</i></span>';
    }
    return $h . '</div>';
}
