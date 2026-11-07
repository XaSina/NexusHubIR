<?php
/**
 * NEXUS | HUB — security
 *   · security headers on every response
 *   · per-IP rate limits (in the login_attempts table, keys are hashed)
 *   · captcha: a distorted picture drawn on the server (no outside service), single use, expires, bound to the session
 *   · hidden admin entrance: a secret address hands out a one-time login link that changes on every visit
 */

/* =========================================================================
   Headers
   ========================================================================= */
function security_headers(): void
{
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    // a light policy that never blocks the site's own inline scripts/styles: no framing by other sites, no <base> or plugin
    // tricks, forms may only post back to this site
    header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'" . (is_https() ? '; upgrade-insecure-requests' : ''));
    if (is_https()) header('Strict-Transport-Security: max-age=15552000');
}

/* =========================================================================
   Rate limits
   ========================================================================= */
/** count one hit for this visitor; false when they went over $max within $window seconds */
function rate_hit(string $name, int $max, int $window, string $who = ''): bool
{
    $k = 'r:' . substr(hash('sha256', $name . '|' . ($who !== '' ? $who : client_ip())), 0, 40);
    try {
        $r = row('SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?', [$k]);
        if ($r && time() - strtotime((string) $r['last_attempt']) > $window) { q('DELETE FROM login_attempts WHERE ip = ?', [$k]); $r = null; }
        if ($r && (int) $r['attempts'] >= $max) return false;
        q('INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1' . ($r ? '' : ', last_attempt = VALUES(last_attempt)'), [$k, now()]);
    } catch (Throwable $e) { return true; }   // never lock people out because of a database hiccup
    return true;
}

/** has this visitor already gone over the limit? (does not count a hit) */
function rate_over(string $name, int $max, int $window, string $who = ''): int
{
    $k = 'r:' . substr(hash('sha256', $name . '|' . ($who !== '' ? $who : client_ip())), 0, 40);
    $r = row('SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?', [$k]);
    if (!$r) return 0;
    $left = $window - (time() - strtotime((string) $r['last_attempt']));
    return $left > 0 && (int) $r['attempts'] >= $max ? $left : 0;
}

function rate_clear(string $name, string $who = ''): void
{
    q('DELETE FROM login_attempts WHERE ip = ?', ['r:' . substr(hash('sha256', $name . '|' . ($who !== '' ? $who : client_ip())), 0, 40)]);
}

/* =========================================================================
   Captcha
   ========================================================================= */
function captcha_contexts(): array { return ['login', 'register', 'admin']; }

function captcha_on(string $ctx): bool
{
    return $ctx === 'admin' ? setting_on('captcha_admin') : setting_on('captcha_site');
}

/** a new code for this form (each picture request makes a new one) */
function captcha_new(string $ctx): string
{
    $set = '23456789';
    $len = max(4, min(6, (int) setting('captcha_len', 5)));
    $c = ''; for ($i = 0; $i < $len; $i++) $c .= $set[random_int(0, strlen($set) - 1)];
    $_SESSION['cap'][$ctx] = ['c' => $c, 'exp' => time() + 600];
    return $c;
}

/** check what the visitor typed; the code is spent on every check, right or wrong (no guessing on one picture) */
function captcha_check(string $ctx, string $typed): bool
{
    if (!captcha_on($ctx)) return true;
    $c = $_SESSION['cap'][$ctx] ?? null;
    unset($_SESSION['cap'][$ctx]);
    if (!$c || ($c['exp'] ?? 0) < time()) return false;
    $typed = preg_replace('~\D~', '', en_num(trim($typed)));
    return $typed !== '' && hash_equals((string) $c['c'], $typed);
}

/** the picture: noisy, warped, every digit at its own angle; drawn fresh each time */
function captcha_png(string $code): string
{
    $W = 240; $H = 80;
    $font = NX_APP . '/fonts/captcha.ttf';
    $tt = function_exists('imagettftext') && is_file($font);
    $im = imagecreatetruecolor($W, $H);
    $p = sscanf(ltrim(valid_hex((string) (theme_colors()['primary'] ?? '#ff1f3d'), '#ff1f3d'), '#'), '%02x%02x%02x');
    // background: deep night with a tint of the theme colour
    for ($y = 0; $y < $H; $y++) {
        $t = $y / $H;
        $col = imagecolorallocate($im, (int) (8 + $p[0] * .10 * (1 - $t)), (int) (10 + $p[1] * .08 * (1 - $t)), (int) (18 + $p[2] * .12 * (1 - $t)));
        imageline($im, 0, $y, $W, $y, $col);
    }
    // faint HUD grid
    $grid = imagecolorallocatealpha($im, $p[0], $p[1], $p[2], 112);
    for ($x = 0; $x < $W; $x += 12) imageline($im, $x, 0, $x, $H, $grid);
    for ($y = 0; $y < $H; $y += 12) imageline($im, 0, $y, $W, $y, $grid);
    // noise: arcs and dust
    imagesetthickness($im, 2);
    for ($i = 0; $i < 7; $i++) {
        $c = imagecolorallocatealpha($im, random_int(80, 255), random_int(80, 255), random_int(120, 255), random_int(70, 100));
        imagearc($im, random_int(-40, $W + 40), random_int(-20, $H + 20), random_int(80, 260), random_int(40, 140), random_int(0, 360), random_int(0, 360), $c);
    }
    imagesetthickness($im, 1);
    for ($i = 0; $i < 520; $i++) { $c = imagecolorallocatealpha($im, random_int(120, 255), random_int(120, 255), random_int(120, 255), random_int(60, 110)); imagesetpixel($im, random_int(0, $W - 1), random_int(0, $H - 1), $c); }
    // the digits
    $n = strlen($code); $step = ($W - 36) / $n;
    for ($i = 0; $i < $n; $i++) {
        $ch = $code[$i];
        $x = (int) (18 + $i * $step + random_int(-3, 5));
        $mix = random_int(0, 100) / 100;
        $col = imagecolorallocate($im, (int) (255 * (1 - $mix) + $p[0] * $mix * .6 + 60 * $mix), (int) (255 * (1 - $mix) + $p[1] * $mix * .6 + 60 * $mix), (int) (255 * (1 - $mix) + $p[2] * $mix * .6 + 60 * $mix));
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 40);
        if ($tt) {
            $size = random_int(28, 36); $ang = random_int(-30, 30); $y = random_int(50, 64);
            imagettftext($im, $size, $ang, $x + 2, $y + 2, $shadow, $font, $ch);
            imagettftext($im, $size, $ang, $x, $y, $col, $font, $ch);
        } else {                                                   // no FreeType: a tiny built-in glyph, blown up and tilted
            $g = imagecreatetruecolor(10, 16); imagefill($g, 0, 0, imagecolorallocatealpha($g, 0, 0, 0, 127)); imagesavealpha($g, true);
            imagestring($g, 5, 1, 0, $ch, imagecolorallocate($g, 255, 255, 255));
            $g2 = imagerotate($g, random_int(-25, 25), imagecolorallocatealpha($g, 0, 0, 0, 127));
            imagecopyresampled($im, $g2, $x, random_int(10, 22), 0, 0, 30, 46, imagesx($g2), imagesy($g2));
        }
    }
    // two wavy strike lines through the text
    imagesetthickness($im, 2);
    for ($k = 0; $k < 2; $k++) {
        $c = imagecolorallocatealpha($im, $p[0], $p[1], $p[2], 30);
        $amp = random_int(6, 14); $per = random_int(30, 60) / 10; $off = random_int(24, 56); $ph = random_int(0, 628) / 100;
        $px = 0; $py = (int) ($off + sin($ph) * $amp);
        for ($x = 4; $x <= $W; $x += 4) { $y = (int) ($off + sin($ph + $x / ($W / $per)) * $amp); imageline($im, $px, $py, $x, $y, $c); $px = $x; $py = $y; }
    }
    // gentle wave warp, column by column
    $out = imagecreatetruecolor($W, $H);
    imagecopy($out, $im, 0, 0, 0, 0, $W, $H);
    $amp = random_int(2, 4); $per = random_int(26, 40); $ph = random_int(0, 628) / 100;
    for ($x = 0; $x < $W; $x++) { $dy = (int) round(sin($ph + $x / $per) * $amp); imagecopy($out, $im, $x, $dy, $x, 0, 1, $H); }
    ob_start(); imagepng($out, null, 6); $png = (string) ob_get_clean();
    imagedestroy($im); imagedestroy($out);
    return $png;
}

/** the /captcha route */
function captcha_serve(): never
{
    $ctx = (string) ($_GET['c'] ?? '');
    if (!in_array($ctx, captcha_contexts(), true)) { http_response_code(404); exit; }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (!rate_hit('capimg', 80, 600)) { http_response_code(429); exit; }
    $code = captcha_new($ctx);
    if (!function_exists('imagecreatetruecolor')) {               // no GD at all: a plain text picture (still single use)
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="80"><rect width="240" height="80" fill="#0b0d14"/><text x="120" y="52" font-size="34" font-family="monospace" fill="#fff" text-anchor="middle" letter-spacing="8">' . $code . '</text></svg>';
        exit;
    }
    header('Content-Type: image/png');
    echo captcha_png($code);
    exit;
}

/** the captcha block for a form (picture + refresh + field) */
function captcha_html(string $ctx, string $inputClass = '', string $label = 'کد امنیتی'): string
{
    if (!captcha_on($ctx)) return '';
    $src = url('captcha') . '?c=' . $ctx;
    return '<div class="nx-cap" data-cap="' . e($ctx) . '" data-src="' . e($src) . '">'
        . '<div class="nxc-frame"><img class="nxc-img" src="' . e($src) . '&amp;v=' . bin2hex(random_bytes(4)) . '" alt="کد امنیتی" width="240" height="80" draggable="false">'
        . '<i class="nxc-scan" aria-hidden="true"></i><i class="nxc-c nxc-c1"></i><i class="nxc-c nxc-c2"></i><i class="nxc-c nxc-c3"></i><i class="nxc-c nxc-c4"></i>'
        . '<button type="button" class="nxc-re" title="کد جدید" aria-label="کد جدید"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 4v5h-5"/></svg></button></div>'
        . '<label class="nxc-field ' . e($inputClass) . '"><span class="nxc-ic"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/></svg></span>'
        . '<input name="captcha" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="8" placeholder="' . e($label) . '" dir="ltr" required></label>'
        . '</div>';
}

/* =========================================================================
   Hidden admin entrance
   ========================================================================= */
/** the gate is on unless the owner turned it off — or left the unlock file in app/ (recovery) */
function admin_gate_on(): bool
{
    if (is_file(NX_APP . '/admin-unlock.txt')) return false;
    return setting_on('admin_gate_on') && admin_gate_key() !== '';
}

function admin_gate_key(): string
{
    $k = (string) setting('admin_gate_key');
    if ($k === '') { $k = admin_gate_newkey(); save_settings(['admin_gate_key' => $k]); }
    return $k;
}

function admin_gate_newkey(): string
{
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    $k = 'nx-'; for ($i = 0; $i < 14; $i++) $k .= $abc[random_int(0, strlen($abc) - 1)];
    return $k;
}

function admin_gate_url(): string { return site_url(admin_gate_key()); }

/** the secret address was opened: hand out a fresh one-time login link (it changes on every visit) */
function admin_gate_open(): never
{
    if (!rate_hit('gate', 30, 600)) { http_response_code(429); exit('Too many requests'); }
    $t = bin2hex(random_bytes(16));
    $_SESSION['gate'] = ['t' => $t, 'exp' => time() + 900];
    header('Cache-Control: no-store');
    header('Location: ' . url('admin/login.php') . '?t=' . $t);
    exit;
}

/** is this request carrying the visitor's current one-time ticket? */
function admin_gate_ticket_ok(): bool
{
    $g = $_SESSION['gate'] ?? null;
    $t = (string) ($_GET['t'] ?? '');
    return $g && $t !== '' && ($g['exp'] ?? 0) > time() && hash_equals((string) $g['t'], $t);
}

/** looks exactly like any missing address (nothing tells a scanner the panel lives here) */
function admin_gate_hide(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>404</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#050507;color:#999;font-family:tahoma,sans-serif">'
        . '<div style="text-align:center"><h1 style="margin:0;font-size:64px;color:#333">404</h1><p>صفحه پیدا نشد.</p><a href="' . e(url('')) . '" style="color:#888">بازگشت به سایت</a></div></body></html>';
    exit;
}

/* =========================================================================
   Panel sign-ins log (last 30)
   ========================================================================= */
function admin_login_log(array $u, bool $ok): void
{
    $list = json_decode((string) setting('admin_login_log', '[]'), true) ?: [];
    array_unshift($list, ['u' => (string) $u['username'], 'ok' => $ok, 'ip' => client_ip(), 'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120), 'at' => now()]);
    save_settings(['admin_login_log' => json_encode(array_slice($list, 0, 30), JSON_UNESCAPED_UNICODE)]);
}

/** a short readable name for a browser string */
function ua_short(string $ua): string
{
    $os = preg_match('~Android~i', $ua) ? 'Android' : (preg_match('~iPhone|iPad~i', $ua) ? 'iOS' : (preg_match('~Windows~i', $ua) ? 'Windows' : (preg_match('~Mac OS~i', $ua) ? 'macOS' : (preg_match('~Linux~i', $ua) ? 'Linux' : '?'))));
    $br = preg_match('~Edg/~', $ua) ? 'Edge' : (preg_match('~OPR/|Opera~', $ua) ? 'Opera' : (preg_match('~Firefox/~', $ua) ? 'Firefox' : (preg_match('~Chrome/~', $ua) ? 'Chrome' : (preg_match('~Safari/~', $ua) ? 'Safari' : 'مرورگر'))));
    return $br . ' · ' . $os;
}
