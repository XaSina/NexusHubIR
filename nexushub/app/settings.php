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
            '--surface-rgb' => '255,255,255', '--line' => 'rgba(18,19,26,.10)', '--shadow' => '0 20px 50px rgba(30,30,60,.12)',
        ];
    } else {
        $vars += [
            '--text' => '#f4f4f7', '--text-rgb' => '244,244,247', '--muted' => '#9d9dab',
            '--surface-rgb' => '18,18,24', '--line' => 'rgba(255,255,255,.08)', '--shadow' => '0 25px 60px rgba(0,0,0,.55)',
        ];
    }
    $css = ':root{';
    foreach ($vars as $k => $v) $css .= $k . ':' . $v . ';';
    return $css . '}';
}
