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

/** the picture: noisy, warped, every digit at its own angle; drawn fresh each time at double size (sharp on phones) */
function captcha_png(string $code): string
{
    $W = 360; $H = 112;                                   // shown at 180×56
    $font = NX_APP . '/fonts/captcha.ttf';
    $tt = function_exists('imagettftext') && is_file($font);
    $im = imagecreatetruecolor($W, $H);
    $p = sscanf(ltrim(valid_hex((string) (theme_colors()['primary'] ?? '#ff1f3d'), '#ff1f3d'), '#'), '%02x%02x%02x');
    for ($y = 0; $y < $H; $y++) {                         // deep night with a tint of the theme colour
        $t = $y / $H;
        imageline($im, 0, $y, $W, $y, imagecolorallocate($im, (int) (10 + $p[0] * .12 * (1 - $t)), (int) (12 + $p[1] * .09 * (1 - $t)), (int) (22 + $p[2] * .14 * (1 - $t))));
    }
    $grid = imagecolorallocatealpha($im, $p[0], $p[1], $p[2], 114);
    for ($x = 0; $x < $W; $x += 18) imageline($im, $x, 0, $x, $H, $grid);
    for ($y = 0; $y < $H; $y += 18) imageline($im, 0, $y, $W, $y, $grid);
    imagesetthickness($im, 3);
    for ($i = 0; $i < 6; $i++) {
        $c = imagecolorallocatealpha($im, random_int(90, 255), random_int(90, 255), random_int(130, 255), random_int(78, 104));
        imagearc($im, random_int(-60, $W + 60), random_int(-30, $H + 30), random_int(120, 380), random_int(60, 200), random_int(0, 360), random_int(0, 360), $c);
    }
    imagesetthickness($im, 1);
    for ($i = 0; $i < 900; $i++) { $c = imagecolorallocatealpha($im, random_int(130, 255), random_int(130, 255), random_int(130, 255), random_int(64, 112)); imagefilledellipse($im, random_int(0, $W - 1), random_int(0, $H - 1), 2, 2, $c); }
    // the digits fill the picture edge to edge (no empty margins)
    $n = strlen($code); $step = ($W - 64) / $n;
    for ($i = 0; $i < $n; $i++) {
        $ch = $code[$i];
        $x = (int) (26 + $i * $step + random_int(-3, 5));
        $mix = random_int(0, 70) / 100;
        $col = imagecolorallocate($im, (int) min(255, 255 * (1 - $mix) + ($p[0] * .7 + 70) * $mix), (int) min(255, 255 * (1 - $mix) + ($p[1] * .7 + 70) * $mix), (int) min(255, 255 * (1 - $mix) + ($p[2] * .7 + 70) * $mix));
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 30);
        if ($tt) {
            $size = random_int(48, 58); $ang = random_int(-28, 28); $y = random_int(78, 94);
            imagettftext($im, $size, $ang, $x + 3, $y + 3, $shadow, $font, $ch);
            imagettftext($im, $size, $ang, $x, $y, $col, $font, $ch);
        } else {
            $g = imagecreatetruecolor(10, 16); imagefill($g, 0, 0, imagecolorallocatealpha($g, 0, 0, 0, 127)); imagesavealpha($g, true);
            imagestring($g, 5, 1, 0, $ch, imagecolorallocate($g, 255, 255, 255));
            $g2 = imagerotate($g, random_int(-25, 25), imagecolorallocatealpha($g, 0, 0, 0, 127));
            imagecopyresampled($im, $g2, $x, random_int(14, 26), 0, 0, 46, 70, imagesx($g2), imagesy($g2));
        }
    }
    imagesetthickness($im, 3);
    for ($k = 0; $k < 2; $k++) {                          // two wavy strike lines through the text
        $c = imagecolorallocatealpha($im, $p[0], $p[1], $p[2], 34);
        $amp = random_int(8, 18); $per = random_int(30, 60) / 10; $off = random_int(36, 80); $ph = random_int(0, 628) / 100;
        $px = 0; $py = (int) ($off + sin($ph) * $amp);
        for ($x = 6; $x <= $W; $x += 6) { $y = (int) ($off + sin($ph + $x / ($W / $per)) * $amp); imageline($im, $px, $py, $x, $y, $c); $px = $x; $py = $y; }
    }
    $out = imagecreatetruecolor($W, $H);                  // gentle wave warp, column by column
    imagecopy($out, $im, 0, 0, 0, 0, $W, $H);
    $amp = random_int(3, 6); $per = random_int(38, 58); $ph = random_int(0, 628) / 100;
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
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="56"><rect width="180" height="56" fill="#0b0d14"/><text x="90" y="38" font-size="28" font-family="monospace" fill="#fff" text-anchor="middle" letter-spacing="8">' . $code . '</text></svg>';
        exit;
    }
    header('Content-Type: image/png');
    echo captcha_png($code);
    exit;
}

/** the captcha block for a form: picture (its frame is exactly the picture) + refresh beside it, then the field */
function captcha_html(string $ctx, string $inputClass = '', string $label = 'کد امنیتی رو وارد کن'): string
{
    if (!captcha_on($ctx)) return '';
    $src = url('captcha') . '?c=' . $ctx;
    return '<div class="nx-cap" data-cap="' . e($ctx) . '" data-src="' . e($src) . '">'
        . '<div class="nxc-top"><div class="nxc-frame"><img class="nxc-img" src="' . e($src) . '&amp;v=' . bin2hex(random_bytes(4)) . '" alt="کد امنیتی" width="180" height="56" draggable="false">'
        . '<i class="nxc-scan" aria-hidden="true"></i><i class="nxc-c nxc-c1"></i><i class="nxc-c nxc-c2"></i><i class="nxc-c nxc-c3"></i><i class="nxc-c nxc-c4"></i></div>'
        . '<button type="button" class="nxc-re" title="کد جدید" aria-label="کد جدید"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 4v5h-5"/></svg><span>کد جدید</span></button></div>'
        . '<label class="nxc-field ' . e($inputClass) . '"><span class="nxc-ic"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/></svg></span>'
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
    return setting_on('admin_gate_on') && admin_gate_hash() !== '';
}

/** only a fingerprint of the secret address is kept (in the database and the code); the address itself is never stored */
function admin_gate_hash(): string { return (string) setting('admin_gate_hash'); }

function admin_gate_match(string $seg): bool
{
    $h = admin_gate_hash();
    return $h !== '' && strlen($seg) >= 24 && hash_equals($h, hash('sha256', $seg));
}

/** a new 48-character secret address word */
function admin_gate_newkey(): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $k = ''; for ($i = 0; $i < 48; $i++) $k .= $abc[random_int(0, strlen($abc) - 1)];
    return $k;
}

/** set a new secret address; returns the full link (shown once, never saved) */
function admin_gate_reset(): string
{
    $k = admin_gate_newkey();
    save_settings(['admin_gate_hash' => hash('sha256', $k), 'admin_gate_key' => '']);
    return site_url($k);
}

/** the secret address was opened: hand out a fresh one-time login link */
function admin_gate_open(): never
{
    if (!rate_hit('gate', 40, 600)) { http_response_code(429); exit('Too many requests'); }
    admin_gate_ticket_go();
}

/** a brand-new long one-time login address for this browser, and go there */
function admin_gate_ticket_go(): never
{
    $t = bin2hex(random_bytes(48));                      // 96 characters, new every time
    $_SESSION['gate'] = ['t' => $t, 'exp' => time() + 900, 'seen' => false];
    header('Cache-Control: no-store');
    header('Location: ' . url('admin/login.php') . '?t=' . $t);
    exit;
}

/**
 * is this request carrying this browser's current ticket? A refresh (the same address opened again) swaps it for a new one,
 * so the login address changes on every refresh; the address copied into another browser is a plain 404.
 */
function admin_gate_ticket_ok(): bool
{
    $g = $_SESSION['gate'] ?? null;
    $t = (string) ($_GET['t'] ?? '');
    if (!$g || $t === '' || ($g['exp'] ?? 0) < time() || !hash_equals((string) $g['t'], $t)) return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        if (!empty($g['seen'])) admin_gate_ticket_go();     // refreshed: new address
        $_SESSION['gate']['seen'] = true;
    }
    return true;
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
