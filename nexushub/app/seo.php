<?php
/**
 * NEXUS | HUB — SEO: structured data (JSON-LD), extra meta tags, IndexNow pings.
 * Everything here is built from data the site already has; nothing needs filling in per post.
 */

/** other names people type for the brand (Google uses these to match brand searches to the site) */
/** the site name Google should show: the panel's choice, else the first brand name when the title has separators */
function seo_site_name(): string
{
    $n = trim((string) setting('seo_site_name'));
    if ($n !== '') return $n;
    $t = trim((string) setting('site_title'));
    if (!preg_match('~[|—–-]~u', $t)) return $t;
    $alt = array_values(array_filter(array_map('trim', preg_split('~[,،\n]+~u', (string) setting('seo_alt_names', '')))));
    return $alt[0] ?? trim(preg_replace('~\s*[|—–-]\s*~u', '', $t));
}

function seo_alt_names(): array
{
    $list = array_filter(array_map('trim', preg_split('~[,،\n]+~u', (string) setting('seo_alt_names', ''))));
    $host = (string) parse_url(site_url(''), PHP_URL_HOST);
    if ($host && !preg_match('~^(localhost|127\.|10\.|192\.168\.)~', $host)) $list[] = preg_replace('~^www\.~', '', $host);
    $list[] = (string) setting('site_title');
    $main = seo_site_name();
    return array_values(array_unique(array_filter($list, fn($n) => $n !== '' && $n !== $main)));
}

/** Organization behind the site (logo + social profiles) */
function seo_org(string $logoUrl): array
{
    $same = [];
    foreach (social_links() as $s) if (preg_match('~^https?://~i', (string) $s['url'])) $same[] = $s['url'];
    return array_filter([
        '@type' => 'Organization', '@id' => site_url('#org'), 'name' => seo_site_name(), 'alternateName' => seo_alt_names() ?: null, 'url' => site_url(''),
        'logo' => ['@type' => 'ImageObject', 'url' => $logoUrl, 'width' => 512, 'height' => 512],
        'sameAs' => $same ?: null,
    ]);
}

/** One <script type="application/ld+json"> with every entity that fits the page */
function seo_jsonld(array $meta, string $title, string $desc, string $image, string $logoUrl): string
{
    $graph = [];
    $graph[] = [
        '@type' => 'WebSite', '@id' => site_url('#website'), 'url' => site_url(''), 'name' => seo_site_name(), 'alternateName' => seo_alt_names(),
        'description' => (string) setting('site_description'), 'inLanguage' => 'fa-IR', 'publisher' => ['@id' => site_url('#org')],
        'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => site_url('search') . '?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
    ];
    $graph[] = seo_org($logoUrl);

    if (!empty($meta['post'])) {
        $p = $meta['post'];
        $url = site_url('post/' . rawurlencode($p['slug']));
        $authorName = trim((string) ($p['author_name'] ?? '')) ?: (string) setting('site_title');
        $graph[] = array_filter([
            '@type' => 'NewsArticle', '@id' => $url . '#article', 'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'headline' => mb_substr($title, 0, 110), 'description' => $desc, 'image' => [$image],
            'datePublished' => date('c', strtotime((string) ($p['published_at'] ?? 'now'))),
            'dateModified' => date('c', strtotime((string) ($p['updated_at'] ?? $p['published_at'] ?? 'now'))),
            'author' => array_filter(['@type' => 'Person', 'name' => $authorName, 'url' => !empty($p['author_id']) ? site_url('author/' . (int) $p['author_id']) : null]),
            'publisher' => ['@id' => site_url('#org')],
            'articleSection' => $p['cat_name'] ?? null,
            'keywords' => !empty($meta['tags']) ? implode('، ', array_column($meta['tags'], 'name')) : null,
            'inLanguage' => 'fa-IR', 'isAccessibleForFree' => true,
        ], fn($v) => $v !== null && $v !== '');
    }

    if (!empty($meta['product'])) {
        $p = $meta['product'];
        $from = function_exists('product_from_price') ? product_from_price($p) : null;
        $graph[] = array_filter([
            '@type' => 'Product', '@id' => site_url('shop/' . rawurlencode($p['slug'])) . '#product', 'name' => $p['title'],
            'description' => $desc, 'image' => $image ? [$image] : null, 'sku' => 'NX-' . $p['id'], 'category' => $p['cat_name'] ?? null,
            'brand' => ['@type' => 'Brand', 'name' => (string) setting('site_title')],
            'offers' => $from !== null ? [
                '@type' => 'Offer', 'url' => site_url('shop/' . rawurlencode($p['slug'])), 'priceCurrency' => 'IRR', 'price' => (string) ($from * 10),   // prices are kept in toman
                'availability' => ($p['status'] ?? '') === 'soldout' ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
                'seller' => ['@id' => site_url('#org')],
            ] : null,
        ], fn($v) => $v !== null && $v !== '');
    }

    if (!empty($meta['crumbs'])) {
        $items = [];
        foreach (array_values($meta['crumbs']) as $i => [$name, $u]) $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $u];
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    return '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}

/** Extra meta tags: article dates/section/tags, locale, Twitter card text */
function seo_meta_tags(array $meta, string $title, string $desc, string $image): string
{
    $o = '<meta property="og:locale" content="fa_IR">' . "\n";
    $o .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n" . '<meta name="twitter:description" content="' . e(str_excerpt($desc, 200)) . '">' . "\n" . '<meta name="twitter:image" content="' . e($image) . '">' . "\n";
    if (!empty($meta['post'])) {
        $p = $meta['post'];
        $o .= '<meta property="article:published_time" content="' . e(date('c', strtotime((string) $p['published_at']))) . '">' . "\n";
        $o .= '<meta property="article:modified_time" content="' . e(date('c', strtotime((string) ($p['updated_at'] ?? $p['published_at'])))) . '">' . "\n";
        if (!empty($p['cat_name'])) $o .= '<meta property="article:section" content="' . e($p['cat_name']) . '">' . "\n";
        foreach (array_slice($meta['tags'] ?? [], 0, 8) as $t) $o .= '<meta property="article:tag" content="' . e($t['name']) . '">' . "\n";
    }
    if (!empty($meta['prev'])) $o .= '<link rel="prev" href="' . e($meta['prev']) . '">' . "\n";
    if (!empty($meta['next'])) $o .= '<link rel="next" href="' . e($meta['next']) . '">' . "\n";
    return $o;
}

/** canonical + prev/next for a paginated list (page 1 has no ?page=) */
function seo_list_meta(string $path, array $pg): array
{
    $page = (int) ($pg['page'] ?? 1); $pages = (int) ($pg['pages'] ?? 1);
    $u = fn(int $n) => site_url($path) . ($n > 1 ? '?page=' . $n : '');
    return ['url' => $u($page), 'prev' => $page > 1 ? $u($page - 1) : '', 'next' => $page < $pages ? $u($page + 1) : ''];
}

/* ---------- IndexNow: tell Bing / Yandex (and others sharing the protocol) the moment a post is published ---------- */
function indexnow_key(): string
{
    $k = (string) setting('indexnow_key', '');
    if (!preg_match('~^[a-f0-9]{32}$~', $k)) {
        $k = bin2hex(random_bytes(16));
        try { save_settings(['indexnow_key' => $k]); } catch (Throwable $e) {}
    }
    return $k;
}

/** returns a short status line for the panel; never throws, never waits more than ~3 s */
function indexnow_ping(array $urls): string
{
    if (!setting_on('indexnow_on') || !$urls || !function_exists('curl_init')) return '';
    $host = parse_url(site_url(''), PHP_URL_HOST);
    if (!$host || preg_match('~^(localhost|127\.|10\.|192\.168\.)~', $host)) return 'local';
    $key = indexnow_key();
    $body = json_encode(['host' => $host, 'key' => $key, 'keyLocation' => site_url($key . '.txt'), 'urlList' => array_values(array_slice($urls, 0, 100))], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.indexnow.org/indexnow');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    try { save_settings(['indexnow_last' => date('Y-m-d H:i:s') . ' · ' . $code]); } catch (Throwable $e) {}
    return (string) $code;
}
