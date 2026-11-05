<?php
/**
 * توابع کمکی عمومی
 */

function config(?string $key = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = is_file(NX_APP . '/config.php') ? (require NX_APP . '/config.php') : [];
    }
    return $key === null ? $cfg : ($cfg[$key] ?? null);
}

/** مسیر پایه‌ی سایت نسبت به دامنه (مثلاً «/nexushub» در XAMPP یا «» روی هاست) */
function nx_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $fileReal = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $fileDir = str_replace('\\', '/', dirname($fileReal));
    $root = str_replace('\\', '/', realpath(NX_ROOT) ?: NX_ROOT);
    $rel = '';
    if ($fileReal !== '' && stripos($fileDir, $root) === 0) {
        $rel = substr($fileDir, strlen($root));
    }
    if ($rel !== '' && strtolower(substr($scriptDir, -strlen($rel))) === strtolower($rel)) {
        $scriptDir = substr($scriptDir, 0, -strlen($rel));
    }
    $base = rtrim($scriptDir, '/');
    return $base;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** آدرس کامل سایت (با دامنه) */
function site_url(string $path = ''): string
{
    $override = rtrim((string) config('site_url'), '/');
    if ($override !== '') {
        $baseFull = $override;
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // the addresses we hand out (sitemap, canonical, share links) always use https on the real domain, even when a
        // page was opened over plain http: Google then keeps one version of each page (the https one)
        $local = (bool) preg_match('~^(localhost|127\.|\[?::1|10\.|192\.168\.)~', $host);
        $https = is_https() || (!$local && function_exists('setting_on') && setting_on('seo_https'));
        $baseFull = ($https ? 'https' : 'http') . '://' . $host . nx_base_path();
    }
    return $baseFull . '/' . ltrim($path, '/');
}

/** آدرس نسبی داخل سایت */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return nx_base_path() . '/' . $path;
}

function asset(string $path): string
{
    $file = NX_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : NX_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** آدرس فایل رسانه (آپلود شده یا خارجی) */
function media_url(?string $path): string
{
    if (!$path) return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return url($path);
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $to): never
{
    if (!preg_match('~^https?://~', $to) && !str_starts_with($to, '/')) $to = url($to);
    header('Location: ' . $to);
    exit;
}

function nx_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('nxsess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => nx_base_path() . '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(403);
        exit('نشست شما منقضی شده است. صفحه را رفرش کنید و دوباره تلاش کنید.');
    }
}

/* ---------- Flash ---------- */
function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['_flash'][] = ['msg' => $msg, 'type' => $type];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- متن ---------- */
function slugify(string $text): string
{
    $text = en_num(trim($text));
    $text = str_replace(["\u{200C}", "\u{200F}", "\u{200E}"], '-', $text);
    $text = mb_strtolower($text);
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
    $text = trim(preg_replace('~-+~', '-', $text), '-');
    if (mb_strlen($text) > 120) $text = rtrim(mb_substr($text, 0, 120), '-');
    return $text !== '' ? $text : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function unique_slug(string $table, string $slug, int $exceptId = 0): string
{
    $base = $slug;
    $n = 1;
    while ((int) val("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $exceptId]) > 0) {
        $n++;
        $slug = $base . '-' . $n;
    }
    return $slug;
}

/** Remove embed shortcodes such as [video src="…" title="…"] so they never show up as text in summaries */
function strip_shortcodes(string $s): string
{
    return (string) preg_replace('~\[\s*(?:video|audio|embed|gallery)\b[^\]]*\]~iu', ' ', $s);
}

function str_excerpt(string $html, int $len = 160): string
{
    $html = strip_shortcodes(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
    $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(preg_replace('~<(br|/p|/h\d|/li)>~i', ' ', $html)), ENT_QUOTES, 'UTF-8')));
    if (mb_strlen($t) <= $len) return $t;
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    if ($sp !== false && $sp > $len * 0.6) $cut = mb_substr($cut, 0, $sp);
    return $cut . '…';
}

function reading_time(string $html): int
{
    $words = count(preg_split('~\s+~u', trim(strip_tags(strip_shortcodes($html))), -1, PREG_SPLIT_NO_EMPTY));
    return max(1, (int) ceil($words / 200));
}

function fa_short_num(int $n): string
{
    if ($n >= 1000000) return fa_num(rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.')) . 'M';
    if ($n >= 1000) return fa_num(rtrim(rtrim(number_format($n / 1000, 1), '0'), '.')) . 'K';
    return fa_num((string) $n);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function ip_hash(): string
{
    return hash('sha256', client_ip() . '|' . (config('secret') ?? 'nx'));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function safe_link(?string $link): string
{
    $link = trim((string) $link);
    if ($link === '') return '';
    if (preg_match('~^(https?://|mailto:|tel:|/|#)~i', $link)) return $link;
    if (preg_match('~^[\w.-]+\.[a-z]{2,}~i', $link)) return 'https://' . $link;
    return '';
}

function is_mobile_ua(): bool
{
    return (bool) preg_match('~Mobile|Android|iPhone|iPad|iPod|Opera Mini|IEMobile~i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

/** صفحه‌بندی */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $pages));
    return ['total' => $total, 'per' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function pagination_html(array $p, string $baseUrl): string
{
    if ($p['pages'] <= 1) return '';
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $link = fn($n) => e($baseUrl . ($n > 1 ? $sep . 'page=' . $n : ''));
    $h = '<nav class="pagination" aria-label="صفحه‌بندی">';
    if ($p['page'] > 1) $h .= '<a class="pg-btn" href="' . $link($p['page'] - 1) . '">‹ قبلی</a>';
    $start = max(1, $p['page'] - 2);
    $end = min($p['pages'], $p['page'] + 2);
    if ($start > 1) $h .= '<a class="pg-num" href="' . $link(1) . '">' . fa_num('1') . '</a>' . ($start > 2 ? '<span class="pg-dots">…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i === $p['page']
            ? '<span class="pg-num active">' . fa_num((string) $i) . '</span>'
            : '<a class="pg-num" href="' . $link($i) . '">' . fa_num((string) $i) . '</a>';
    }
    if ($end < $p['pages']) $h .= ($end < $p['pages'] - 1 ? '<span class="pg-dots">…</span>' : '') . '<a class="pg-num" href="' . $link($p['pages']) . '">' . fa_num((string) $p['pages']) . '</a>';
    if ($p['page'] < $p['pages']) $h .= '<a class="pg-btn" href="' . $link($p['page'] + 1) . '">بعدی ›</a>';
    return $h . '</nav>';
}

/** رندر قالب با داده */
function view(string $name, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include NX_APP . '/views/' . $name . '.php';
    return (string) ob_get_clean();
}

function partial(string $name, array $data = []): void
{
    echo view('partials/' . $name, $data);
}

/** آیکون SVG خطی (مجموعه‌ی کوچک) */
function icon(string $name, int $size = 20): string
{
    static $icons = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'telegram' => '<path d="M21.5 4.5 2.8 11.7c-.9.4-.9 1.5.1 1.8l4.7 1.5 1.8 5.6c.3.8 1.2 1 1.8.4l2.6-2.4 4.9 3.6c.7.5 1.6.1 1.8-.7l3.2-15.3c.2-1-.8-1.8-1.7-1.4Z"/><path d="m9.5 14.8 8-7.3"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'comment' => '<path d="M21 12a8 8 0 0 1-11.8 7L4 20l1.1-4.4A8 8 0 1 1 21 12Z"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
        'link' => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'fire' => '<path d="M12 22c4 0 7-3 7-7 0-4-3-6-4-9-1 2-2 3-4 3 0-2 0-4-2-7-1 4-6 7-6 13 0 4 4 7 9 7Z"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Z"/><circle cx="8" cy="8" r="1.5"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'x' => '<path d="M4 4l16 16M20 4 4 20"/>',
        'whatsapp' => '<path d="M3 21l1.6-4.8A8.5 8.5 0 1 1 8 19.6L3 21Z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2L9 9.5Z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
        'youtube' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'trend' => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'copy' => '<rect x="8" y="8" width="13" height="13" rx="3"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'pin' => '<path d="M12 17v5M5 17h14l-2-4V5l1-2H6l1 2v8l-2 4Z"/>',
        'game' => '<rect x="2" y="7" width="20" height="11" rx="5"/><path d="M7 11v4M5 13h4"/><circle cx="16" cy="12" r="1"/><circle cx="18" cy="14" r="1"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
        'sparkle' => '<path d="M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"/>',
        'send' => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/>',
        'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'bag' => '<path d="M5 7h14l-1.2 12.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.5l2.6 12.2a1.6 1.6 0 0 0 1.6 1.3h9.1a1.6 1.6 0 0 0 1.6-1.2L21 8H5.6"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/>',
        'support' => '<path d="M3 12a9 9 0 1 1 18 0v5a2 2 0 0 1-2 2h-2v-6h4M3 12v5a2 2 0 0 0 2 2h2v-6H3"/>',
        'receipt' => '<path d="M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'trash' => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'minus' => '<path d="M5 12h14"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'box' => '<path d="m21 8-9-5-9 5v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    ];
    $p = $icons[$name] ?? $icons['sparkle'];
    return '<svg class="ic ic-' . $name . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Logo for on-page display: the bundled default is swapped for a sharp 512px version; custom uploads are used as-is */
function logo_url(): string
{
    $l = (string) setting('logo');
    if ($l === '' || in_array($l, ['assets/img/logo-256.png', 'assets/img/logo.jpg'], true)) return url('assets/img/logo-hd.webp');
    return media_url($l);
}

/**
 * <img> for a picture. The bundled animated SVG covers / product art get a still copy on phones:
 * animated SVGs are re-rasterised every frame, which on phone GPUs causes flicker and half-drawn images.
 */
function smart_img(string $src, string $attrs): string
{
    $img = '<img src="' . e(media_url($src)) . '" ' . $attrs . '>';
    if (preg_match('~^assets/img/(covers|presets)/([a-z0-9-]+)\.svg$~', $src, $m)) {
        return '<picture class="pic-still"><source media="(hover: none), (max-width: 900px)" srcset="' . e(url('assets/img/' . $m[1] . '/still/' . $m[2] . '.svg')) . '">' . $img . '</picture>';
    }
    return $img;
}

/* ---------- Trust badges (e.g. Enamad, Samandehi) shown in the footer ----------
   The admin pastes the badge's official code, or just an image address. Only <a> and <img> survive, with a
   short list of attributes and http(s) links only; an onclick="window.open('https://…')" popup (Samandehi
   style) becomes a normal link. referrerpolicy is kept: the issuers check the referring domain. */
function trust_badges(): array
{
    $list = json_decode((string) setting('trust_badges', '[]'), true);
    return is_array($list) ? array_values(array_filter($list, fn($b) => is_array($b) && trim((string) ($b['code'] ?? '')) !== '')) : [];
}

function trust_url_ok(string $u): bool
{
    return (bool) preg_match('~^https?://[^\s"\'<>]+$~i', $u);
}

function trust_badge_html(array $b): string
{
    $code = trim((string) ($b['code'] ?? ''));
    $title = trim((string) ($b['title'] ?? ''));
    $link = trim((string) ($b['link'] ?? ''));
    if ($code === '') return '';
    if (strpos($code, '<') === false) {                       // just an image address (and maybe a link)
        if (!trust_url_ok($code)) return '';
        $img = '<img src="' . e($code) . '" alt="' . e($title) . '" referrerpolicy="origin">';
        return trust_url_ok($link) ? '<a href="' . e($link) . '" target="_blank" rel="noopener" referrerpolicy="origin" title="' . e($title) . '">' . $img . '</a>' : $img;
    }
    if (!class_exists('DOMDocument')) return '';
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="tb">' . $code . '</div>', LIBXML_NONET);
    libxml_clear_errors();
    $root = $doc->getElementById('tb');
    if (!$root) return '';
    $allowed = ['a' => ['href', 'target', 'title', 'referrerpolicy', 'id'], 'img' => ['src', 'alt', 'title', 'referrerpolicy', 'id', 'code', 'width', 'height']];
    $walk = function (DOMNode $n) use (&$walk, $allowed, $doc, $title): string {
        $out = '';
        foreach (iterator_to_array($n->childNodes) as $c) {
            if (!($c instanceof DOMElement)) continue;
            $tag = strtolower($c->tagName);
            if (!isset($allowed[$tag])) { $out .= $walk($c); continue; }   // drop the wrapper, keep what is inside
            $attrs = '';
            $popup = '';
            if ($c->hasAttribute('onclick') && preg_match('~(https?://[^\s"\'<>)]+)~i', $c->getAttribute('onclick'), $m)) $popup = $m[1];
            foreach ($allowed[$tag] as $a) {
                if (!$c->hasAttribute($a)) continue;
                $v = trim($c->getAttribute($a));
                if (in_array($a, ['href', 'src'], true) && !trust_url_ok($v)) continue;
                if ($a === 'target') $v = '_blank';
                if ($a === 'referrerpolicy' && !preg_match('~^[a-z-]+$~', $v)) continue;
                if (in_array($a, ['width', 'height'], true) && !ctype_digit($v)) continue;
                $attrs .= ' ' . $a . '="' . e(mb_substr($v, 0, 600)) . '"';
            }
            if ($tag === 'img') {
                if (strpos($attrs, ' src=') === false) continue;
                if (strpos($attrs, ' alt=') === false) $attrs .= ' alt="' . e($title) . '"';
                if (strpos($attrs, ' referrerpolicy=') === false) $attrs .= ' referrerpolicy="origin"';
                $html = '<img' . $attrs . '>';
                $out .= $popup !== '' && trust_url_ok($popup) ? '<a href="' . e($popup) . '" target="_blank" rel="noopener" referrerpolicy="origin">' . $html . '</a>' : $html;
            } else {
                $out .= '<a' . $attrs . ' rel="noopener">' . $walk($c) . '</a>';
            }
        }
        return $out;
    };
    $html = $walk($root);
    return strpos($html, '<img') !== false ? $html : '';
}
