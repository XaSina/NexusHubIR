<?php
/**
 * NEXUS | HUB — فروشگاه
 * قیمت‌گذاری (تومان / دلار / تون)، سبد خرید، سفارش، اعلان‌ها و فایل‌های خصوصی (فیش‌ها)
 */

function shop_on(): bool
{
    return setting_on('shop_enabled');
}

/* =========================================================================
   Rates: dollar (toman) + TON (USD)
   ========================================================================= */

/** GET a JSON URL with short timeouts (never blocks a page for long). */
function http_json(string $url, int $timeout = 4): ?array
{
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'NexusHub/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) $body = false;
    } elseif (ini_get('allow_url_fopen')) {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => $timeout, 'header' => "Accept: application/json\r\nUser-Agent: NexusHub/1.0\r\n"]]));
    }
    if (!is_string($body) || $body === '') return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

/** TON price in USD from free public APIs (no key). Returns [price, sourceName] or [null, failedSources]. $log gets one line per source. */
function fetch_ton_usd(?array &$log = null, float $budget = 0): array
{
    $start = microtime(true);
    $sources = [
        'CoinGecko' => ['https://api.coingecko.com/api/v3/simple/price?ids=the-open-network&vs_currencies=usd', fn($j) => $j['the-open-network']['usd'] ?? null],
        'Nobitex' => ['https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=usdt', fn($j) => $j['stats']['ton-usdt']['latest'] ?? null],
        'OKX' => ['https://www.okx.com/api/v5/market/ticker?instId=TON-USDT', fn($j) => $j['data'][0]['last'] ?? null],
        'KuCoin' => ['https://api.kucoin.com/api/v1/market/orderbook/level1?symbol=TON-USDT', fn($j) => $j['data']['price'] ?? null],
        'TonAPI' => ['https://tonapi.io/v2/rates?tokens=ton&currencies=usd', fn($j) => $j['rates']['TON']['prices']['USD'] ?? null],
    ];
    $failed = [];
    foreach ($sources as $name => [$url, $pick]) {
        if ($budget > 0 && microtime(true) - $start > $budget) break;   // never hold a page view for long
        $t = microtime(true);
        $j = http_json($url, 3);
        $v = $j ? (float) $pick($j) : 0.0;
        $ms = (int) round((microtime(true) - $t) * 1000);
        if ($v > 0.01 && $v < 10000) { $log[] = [$name, true, $v, $ms]; return [$v, $name]; }
        $log[] = [$name, false, null, $ms];
        $failed[] = $name;
    }
    return [null, $failed];
}

/**
 * USDT (≈ dollar) price in toman from free public APIs of Iranian exchanges (no key), tried in order.
 * Some exchanges answer only Iranian servers and some only foreign ones, so several are tried.
 */
function fetch_usdt_toman(?array &$log = null, float $budget = 0): array
{
    $start = microtime(true);
    $bitpin = function ($j) { foreach ((array) $j as $t) if (is_array($t) && strtoupper((string) ($t['symbol'] ?? '')) === 'USDT_IRT') return $t['price'] ?? null; return null; };
    $sources = [
        'Nobitex' => ['https://api.nobitex.ir/market/stats?srcCurrency=usdt&dstCurrency=rls', fn($j) => $j['stats']['usdt-rls']['latest'] ?? null],
        'Nobitex v3' => ['https://api.nobitex.ir/v3/orderbook/USDTIRT', fn($j) => $j['lastTradePrice'] ?? null],
        'Wallex' => ['https://api.wallex.ir/v1/markets', fn($j) => $j['result']['symbols']['USDTTMN']['stats']['lastPrice'] ?? null],
        'Bitpin' => ['https://api.bitpin.org/api/v1/mkt/tickers/', $bitpin],
        'Tetherland' => ['https://api.tetherland.com/currencies', fn($j) => $j['data']['currencies']['USDT']['price'] ?? null],
    ];
    $failed = [];
    foreach ($sources as $name => [$url, $pick]) {
        if ($budget > 0 && microtime(true) - $start > $budget) break;   // never hold a page view for long
        $t = microtime(true);
        $j = http_json($url, 3);
        $v = $j ? (float) str_replace(',', '', (string) $pick($j)) : 0.0;
        if ($v > 1500000) $v /= 10;        // rial → toman
        $ms = (int) round((microtime(true) - $t) * 1000);
        if ($v > 10000 && $v < 1500000) { $log[] = [$name, true, round($v), $ms]; return [round($v), $name]; }
        $log[] = [$name, false, null, $ms];
        $failed[] = $name;
    }
    return [null, $failed];
}

/**
 * Current rates. usd = toman per dollar, ton = dollars per TON. null = not available.
 * Live prices are cached (TON 5 min, dollar 10 min); a failing source is not retried for 3 minutes,
 * and the last good price (up to 48h old) or the manual price is used meanwhile.
 */
function shop_rates(bool $force = false): array
{
    static $memo = null;
    if ($memo !== null && !$force) return $memo;
    $cache = json_decode((string) setting('shop_rate_cache'), true) ?: [];
    $now = time();
    $dirty = false;
    $out = ['usd' => null, 'usd_src' => 'دستی', 'usd_at' => null, 'ton' => null, 'ton_src' => 'دستی', 'ton_at' => null];

    // --- dollar
    $manualUsd = (float) en_num((string) setting('shop_usd_rate', '0'));
    if (setting('shop_usd_mode', 'manual') === 'auto') {
        $c = $cache['usd'] ?? null;
        $fresh = $c && $now - (int) $c['at'] < 600;
        $retry = $force || $now - (int) ($cache['usd_fail'] ?? 0) > 180;
        if (!$fresh && $retry) {
            $nl = null; [$v, $src] = fetch_usdt_toman($nl, $force ? 0 : 4);
            if ($v) { $cache['usd'] = ['v' => $v, 'src' => $src, 'at' => $now]; unset($cache['usd_fail']); $c = $cache['usd']; }
            else $cache['usd_fail'] = $now;
            $dirty = true;
        }
        if ($c && $now - (int) $c['at'] < 172800) { $out['usd'] = (float) $c['v']; $out['usd_src'] = $c['src'] . ' (تتر)'; $out['usd_at'] = (int) $c['at']; }
    }
    if (!$out['usd'] && $manualUsd > 0) { $out['usd'] = $manualUsd; $out['usd_src'] = 'دستی'; }

    // --- TON
    $manualTon = (float) en_num((string) setting('shop_ton_usd', '0'));
    if (setting('shop_ton_mode', 'auto') === 'auto') {
        $c = $cache['ton'] ?? null;
        $fresh = $c && $now - (int) $c['at'] < 300;
        $retry = $force || $now - (int) ($cache['ton_fail'] ?? 0) > 180;
        if (!$fresh && $retry) {
            $nl = null; [$v, $src] = fetch_ton_usd($nl, $force ? 0 : 4);
            if ($v) { $cache['ton'] = ['v' => $v, 'src' => $src, 'at' => $now]; unset($cache['ton_fail']); $c = $cache['ton']; }
            else $cache['ton_fail'] = $now;
            $dirty = true;
        }
        if ($c && $now - (int) $c['at'] < 172800) { $out['ton'] = (float) $c['v']; $out['ton_src'] = (string) $c['src']; $out['ton_at'] = (int) $c['at']; }
    }
    if (!$out['ton'] && $manualTon > 0) { $out['ton'] = $manualTon; $out['ton_src'] = 'دستی'; }

    if ($dirty) save_settings(['shop_rate_cache' => json_encode($cache)]);
    return $memo = $out;
}

/** A price of the given type → final toman (margin only on dollar/TON prices; rounded up). null = rate missing. */
function to_toman(string $type, float $amount, ?float $margin = null): ?int
{
    if ($amount <= 0) return 0;
    $r = shop_rates();
    $m = $margin ?? (float) setting('shop_margin', '0');
    switch ($type) {
        case 'usd': if (!$r['usd']) return null; $t = $amount * $r['usd']; break;
        case 'ton': if (!$r['usd'] || !$r['ton']) return null; $t = $amount * $r['ton'] * $r['usd']; break;
        default: return (int) round($amount);
    }
    $t *= 1 + max(-90.0, min(500.0, $m)) / 100;
    $step = max(1, (int) setting('shop_round', '1000'));
    return (int) (ceil($t / $step) * $step);
}

function money(?int $toman): string
{
    if ($toman === null) return '—';
    return str_replace(',', '٬', fa_num(number_format($toman)));
}

function price_type_names(): array
{
    return ['toman' => 'تومان (ثابت)', 'usd' => 'دلار ($)', 'ton' => 'تون (TON)'];
}

/** Human form of the source price, e.g. "۱۲ $" or "۴٫۵ TON" (shown under the toman price). */
function source_price_label(string $type, float $amount): string
{
    $n = rtrim(rtrim(number_format($amount, 4, '.', ''), '0'), '.');
    return match ($type) { 'usd' => '$' . $n, 'ton' => $n . ' TON', default => '' };
}

/* =========================================================================
   Products
   ========================================================================= */

function product_margin(array $p): ?float
{
    return $p['margin'] === null || $p['margin'] === '' ? null : (float) $p['margin'];
}

function product_fields(array $p): array
{
    $f = json_decode((string) ($p['fields'] ?? ''), true);
    return is_array($f) ? array_values(array_filter($f, fn($x) => !empty($x['key']) && !empty($x['label']))) : [];
}

function product_custom(array $p): array
{
    $c = json_decode((string) ($p['custom_amount'] ?? ''), true) ?: [];
    return [
        'on' => !empty($c['on']),
        'unit' => (string) ($c['unit'] ?? 'عدد'),
        'price_type' => in_array($c['price_type'] ?? '', ['toman', 'usd', 'ton'], true) ? $c['price_type'] : 'toman',
        'unit_price' => (float) ($c['unit_price'] ?? 0),
        'min' => max(0.000001, (float) ($c['min'] ?? 1)),
        'max' => max(0.000001, (float) ($c['max'] ?? 100000)),
        'step' => max(0.000001, (float) ($c['step'] ?? 1)),
        'presets' => array_values(array_filter(array_map(fn($x) => (float) en_num(trim($x)), explode(',', (string) ($c['presets'] ?? ''))))),
    ];
}

function product_variants(int $pid, bool $activeOnly = true): array
{
    return rows('SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id', [$pid]);
}

function variant_price(array $p, array $v): ?int
{
    return to_toman((string) $v['price_type'], (float) $v['amount'], product_margin($p));
}

function variant_compare(array $p, array $v): ?int
{
    if ($v['compare_amount'] === null || (float) $v['compare_amount'] <= (float) $v['amount']) return null;
    return to_toman((string) $v['price_type'], (float) $v['compare_amount'], product_margin($p));
}

function custom_price(array $p, float $amount): ?int
{
    $c = product_custom($p);
    return to_toman($c['price_type'], $c['unit_price'] * $amount, product_margin($p));
}

/** Data for the live price calculator on the product page: toman per 1 unit before rounding. */
function custom_price_js(array $p): ?array
{
    $c = product_custom($p);
    if (!$c['on']) return null;
    $r = shop_rates();
    $m = 1 + max(-90.0, min(500.0, product_margin($p) ?? (float) setting('shop_margin', '0'))) / 100;
    $raw = match ($c['price_type']) {
        'usd' => $r['usd'] ? $c['unit_price'] * $r['usd'] * $m : null,
        'ton' => $r['usd'] && $r['ton'] ? $c['unit_price'] * $r['ton'] * $r['usd'] * $m : null,
        default => $c['unit_price'],
    };
    return ['unitRaw' => $raw, 'step' => $c['price_type'] === 'toman' ? 0 : max(1, (int) setting('shop_round', '1000')),
        'min' => $c['min'], 'max' => $c['max'], 'inc' => $c['step'], 'unit' => $c['unit']];
}

/** "از ۱۲۰٬۰۰۰ تومان" for cards */
function product_from_price(array $p): ?int
{
    $min = null;
    foreach (product_variants((int) $p['id']) as $v) {
        if ($v['stock'] !== null && (int) $v['stock'] <= 0) continue;
        $t = variant_price($p, $v);
        if ($t !== null && ($min === null || $t < $min)) $min = $t;
    }
    $c = product_custom($p);
    if ($c['on']) {
        $t = custom_price($p, $c['min']);
        if ($t !== null && ($min === null || $t < $min)) $min = $t;
    }
    return $min;
}

function shop_products(array $o = []): array
{
    $w = ["p.status IN ('active','soldout')"];
    $params = [];
    if (!empty($o['category_id'])) { $w[] = 'p.category_id = ?'; $params[] = (int) $o['category_id']; }
    if (!empty($o['featured'])) $w[] = 'p.is_featured = 1';
    if (!empty($o['type'])) { $w[] = 'p.type = ?'; $params[] = $o['type']; }
    if (!empty($o['search'])) { $w[] = '(p.title LIKE ? OR p.subtitle LIKE ?)'; $l = '%' . $o['search'] . '%'; array_push($params, $l, $l); }
    $lim = (int) ($o['limit'] ?? 60);
    return rows('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN shop_categories c ON c.id = p.category_id WHERE '
        . implode(' AND ', $w) . ' ORDER BY p.is_featured DESC, p.sort_order, p.id DESC LIMIT ' . $lim, $params);
}

function product_url(array $p): string
{
    return url('shop/' . rawurlencode((string) $p['slug']));
}

function product_image_html(array $p, string $class = 'pd-img'): string
{
    if (!empty($p['image'])) return '<img class="' . e($class) . '" src="' . e(media_url((string) $p['image'])) . '" alt="' . e($p['title']) . '" loading="lazy" decoding="async">';
    return '<span class="' . e($class) . ' pd-emoji" style="--c:' . e($p['color']) . '"><b>' . e($p['icon']) . '</b></span>';
}

/** Validate the customer's answers for a product's fields. Returns [values, errors]. */
function validate_product_fields(array $p, array $in): array
{
    $vals = []; $err = [];
    foreach (product_fields($p) as $f) {
        $k = (string) $f['key'];
        $v = trim(mb_substr((string) ($in[$k] ?? ''), 0, ($f['type'] ?? '') === 'textarea' ? 1000 : 190));
        $v = en_num($v);
        if ($v === '') { if (!empty($f['required'])) $err[$k] = '«' . $f['label'] . '» را وارد کنید.'; continue; }
        switch ($f['type'] ?? 'text') {
            case 'username':
                $v = '@' . ltrim(preg_replace('~^(https?://)?(t\.me|telegram\.me)/~i', '', $v), '@');
                if (!preg_match('~^@[A-Za-z][A-Za-z0-9_]{3,31}$~', $v)) $err[$k] = 'آیدی تلگرام معتبر نیست (مثل @username).';
                break;
            case 'email': if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $err[$k] = 'ایمیل معتبر نیست.'; break;
            case 'tel': $v = normalize_phone($v); if (!preg_match('~^\+?\d{8,15}$~', $v)) $err[$k] = 'شماره تماس معتبر نیست.'; break;
            case 'number': if (!is_numeric($v)) $err[$k] = '«' . $f['label'] . '» باید عدد باشد.'; break;
            case 'select':
                $opts = array_map('trim', explode(',', (string) ($f['options'] ?? '')));
                if (!in_array($v, $opts, true)) $err[$k] = 'یکی از گزینه‌های «' . $f['label'] . '» را انتخاب کنید.';
                break;
        }
        $vals[$k] = $v;
    }
    return [$vals, $err];
}

/* =========================================================================
   Cart (session)
   ========================================================================= */

function cart_raw(): array
{
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart_raw() as $l) $n += (int) ($l['qty'] ?? 1);
    return $n;
}

/** Add a line; same product+package+answers merges into one line. Returns error message or ''. */
function cart_add(array $p, int $vid, ?float $amount, int $qty, array $fields): string
{
    $qty = max(1, min(20, $qty));
    if ($p['status'] !== 'active') return 'این محصول در حال حاضر قابل سفارش نیست.';
    if ($vid) {
        $v = row('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1', [$vid, $p['id']]);
        if (!$v) return 'بسته انتخابی معتبر نیست.';
        if ($v['stock'] !== null && (int) $v['stock'] < $qty) return (int) $v['stock'] > 0 ? 'فقط ' . fa_num((string) $v['stock']) . ' عدد موجود است.' : 'این بسته ناموجود است.';
        if (variant_price($p, $v) === null) return 'قیمت این بسته هنوز مشخص نیست؛ کمی بعد دوباره تلاش کنید.';
        $amount = null;
    } else {
        $c = product_custom($p);
        if (!$c['on']) return 'یک بسته را انتخاب کنید.';
        if ($amount === null || $amount < $c['min'] - 1e-9 || $amount > $c['max'] + 1e-9) return 'مقدار باید بین ' . fa_num(rtrim(rtrim(number_format($c['min'], 4, '.', ''), '0'), '.')) . ' و ' . fa_num(rtrim(rtrim(number_format($c['max'], 4, '.', ''), '0'), '.')) . ' ' . $c['unit'] . ' باشد.';
        $steps = ($amount - $c['min']) / $c['step'];
        if (abs($steps - round($steps)) > 1e-6) return 'مقدار باید مضربی از ' . fa_num((string) $c['step']) . ' باشد.';
        if (custom_price($p, $amount) === null) return 'قیمت لحظه‌ای هنوز مشخص نیست؛ کمی بعد دوباره تلاش کنید.';
        $qty = 1;
    }
    $key = substr(sha1($p['id'] . '|' . $vid . '|' . (string) $amount . '|' . json_encode($fields, JSON_UNESCAPED_UNICODE)), 0, 12);
    $cart = cart_raw();
    if (isset($cart[$key]) && $vid) $cart[$key]['qty'] = min(20, $cart[$key]['qty'] + $qty);
    else $cart[$key] = ['pid' => (int) $p['id'], 'vid' => $vid, 'amount' => $amount, 'qty' => $qty, 'fields' => $fields, 'at' => time()];
    if (count($cart) > 30) return 'سبد خرید پر است.';
    $_SESSION['cart'] = $cart;
    return '';
}

/** Resolve the cart with current prices. Each line: key, product, variant, title, sub, qty, unit, total, fields, error. */
function cart_lines(): array
{
    $out = [];
    foreach (cart_raw() as $key => $l) {
        $p = row('SELECT * FROM products WHERE id = ?', [(int) $l['pid']]);
        if (!$p) { unset($_SESSION['cart'][$key]); continue; }
        $line = ['key' => $key, 'product' => $p, 'variant' => null, 'title' => $p['title'], 'sub' => '', 'qty' => (int) $l['qty'], 'unit' => null, 'total' => null, 'fields' => $l['fields'] ?? [], 'error' => '', 'amount' => $l['amount']];
        if ($p['status'] !== 'active') $line['error'] = 'این محصول دیگر قابل سفارش نیست.';
        if ($l['vid']) {
            $v = row('SELECT * FROM product_variants WHERE id = ? AND product_id = ?', [(int) $l['vid'], $p['id']]);
            if (!$v || !$v['is_active']) { $line['error'] = 'این بسته دیگر موجود نیست.'; $v = $v ?: null; }
            elseif ($v['stock'] !== null && (int) $v['stock'] < $line['qty']) $line['error'] = (int) $v['stock'] > 0 ? 'فقط ' . fa_num((string) $v['stock']) . ' عدد موجود است.' : 'ناموجود شد.';
            if ($v) { $line['variant'] = $v; $line['sub'] = $v['title']; $line['unit'] = variant_price($p, $v); }
        } else {
            $c = product_custom($p);
            $amt = (float) $l['amount'];
            $line['sub'] = fa_num(rtrim(rtrim(number_format($amt, 4, '.', ''), '0'), '.')) . ' ' . $c['unit'];
            $line['unit'] = $c['on'] ? custom_price($p, $amt) : null;
            if (!$c['on']) $line['error'] = 'خرید مقدار دلخواه برای این محصول غیرفعال شد.';
        }
        if ($line['unit'] === null && !$line['error']) $line['error'] = 'قیمت لحظه‌ای در دسترس نیست.';
        $line['total'] = $line['unit'] === null ? null : $line['unit'] * $line['qty'];
        $out[] = $line;
    }
    return $out;
}

/* =========================================================================
   Orders
   ========================================================================= */

function new_code(string $table, string $prefix): string
{
    do { $c = $prefix . random_int(100000, 999999); } while (val("SELECT id FROM `$table` WHERE code = ?", [$c]));
    return $c;
}

/** Create an order from the cart. Returns [order|null, error]. */
function order_create(array $member, string $note = ''): array
{
    $lines = cart_lines();
    if (!$lines) return [null, 'سبد خرید شما خالی است.'];
    foreach ($lines as $l) if ($l['error']) return [null, $l['title'] . ': ' . $l['error']];
    $total = array_sum(array_column($lines, 'total'));
    if ($total <= 0) return [null, 'مبلغ سفارش معتبر نیست.'];
    $r = shop_rates();
    db()->beginTransaction();
    try {
        $code = new_code('orders', 'NX');
        $oid = insert('orders', [
            'code' => $code, 'member_id' => (int) $member['id'], 'status' => 'pending_payment', 'total' => $total,
            'items_count' => array_sum(array_column($lines, 'qty')), 'customer_note' => mb_substr(trim($note), 0, 1000) ?: null,
            'rates' => json_encode(['usd' => $r['usd'], 'ton' => $r['ton'], 'margin' => (float) setting('shop_margin', '0')]),
            'ip' => client_ip(), 'expires_at' => date('Y-m-d H:i:s', time() + 60 * max(10, (int) setting('shop_pay_minutes', '60'))),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($lines as $l) {
            insert('order_items', [
                'order_id' => $oid, 'product_id' => (int) $l['product']['id'], 'variant_id' => $l['variant']['id'] ?? null,
                'title' => $l['title'], 'variant_title' => $l['sub'], 'icon' => $l['product']['icon'], 'qty' => $l['qty'],
                'unit_price' => $l['unit'], 'total' => $l['total'],
                'fields' => json_encode(order_field_pairs($l['product'], $l['fields']), JSON_UNESCAPED_UNICODE),
            ]);
            if ($l['variant'] && $l['variant']['stock'] !== null) q('UPDATE product_variants SET stock = GREATEST(stock - ?, 0) WHERE id = ?', [$l['qty'], $l['variant']['id']]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    $_SESSION['cart'] = [];
    $o = row('SELECT * FROM orders WHERE id = ?', [$oid]);
    order_notify($o);
    return [$o, ''];
}

/** [[label, value], ...] snapshot of the customer's answers (kept even if the product's fields change later). */
function order_field_pairs(array $p, array $vals): array
{
    $out = [];
    foreach (product_fields($p) as $f) if (($vals[$f['key']] ?? '') !== '') $out[] = [$f['label'], $vals[$f['key']]];
    return $out;
}

function order_items(int $oid): array
{
    return rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$oid]);
}

function order_url(array $o): string
{
    return url('order/' . $o['code']);
}

/** Lazily expire unpaid orders past their payment window (and give reserved stock back). */
function shop_expire_due(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    foreach (rows("SELECT * FROM orders WHERE status = 'pending_payment' AND expires_at < ? LIMIT 50", [now()]) as $o) {
        order_set_status($o, 'expired');
    }
}

function order_restock(array $o): void
{
    foreach (order_items((int) $o['id']) as $it) {
        if ($it['variant_id']) q('UPDATE product_variants SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$it['qty'], $it['variant_id']]);
    }
}

/** Change an order's status + timestamps, stock, sales, notifications (one per order, replaced in place). */
function order_set_status(array $o, string $status, array $extra = []): array
{
    $prev = $o['status'];
    $d = $extra + ['status' => $status, 'updated_at' => now()];
    if ($status === 'reviewing') $d['paid_at'] = now();
    if ($status === 'approved') $d['approved_at'] = now();
    if ($status === 'completed') { $d['completed_at'] = now(); if (!$o['approved_at']) $d['approved_at'] = now(); }
    update('orders', $d, (int) $o['id']);
    if (in_array($status, ['canceled', 'expired'], true) && !in_array($prev, ['canceled', 'expired', 'completed'], true)) order_restock($o);
    if ($status === 'completed' && $prev !== 'completed') {
        foreach (order_items((int) $o['id']) as $it) if ($it['product_id']) q('UPDATE products SET sales = sales + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
    }
    $o = row('SELECT * FROM orders WHERE id = ?', [(int) $o['id']]);
    order_notify($o);
    return $o;
}

function order_notify(array $o): void
{
    $code = $o['code'];
    $link = 'order/' . $code;
    $final = in_array($o['status'], ['completed', 'canceled', 'expired'], true);
    $exp = $final ? date('Y-m-d H:i:s', time() + 86400) : null;
    $msg = match ($o['status']) {
        'pending_payment' => ['💳', 'سفارش ' . $code . ' ثبت شد', 'مبلغ ' . money((int) $o['total']) . ' تومان را کارت‌به‌کارت کنید و تصویر فیش را ارسال کنید.', 'warn'],
        'reviewing' => ['🔎', 'فیش سفارش ' . $code . ' دریافت شد', 'در حال بررسی توسط پشتیبانی؛ نتیجه همین‌جا اطلاع داده می‌شود.', 'info'],
        'rejected' => ['⚠️', 'فیش سفارش ' . $code . ' تایید نشد', ($o['reject_reason'] ?: 'لطفاً فیش صحیح را دوباره ارسال کنید.'), 'error'],
        'approved' => ['⚙️', 'پرداخت سفارش ' . $code . ' تایید شد', 'سفارش شما در حال انجام است.', 'info'],
        'completed' => ['✅', 'سفارش ' . $code . ' انجام شد 🎉', 'برای مشاهده جزئیات سفارش کلیک کنید.', 'success'],
        'canceled' => ['✖️', 'سفارش ' . $code . ' لغو شد', $o['reject_reason'] ?: '', 'muted'],
        'expired' => ['⌛', 'مهلت پرداخت سفارش ' . $code . ' تمام شد', 'در صورت نیاز دوباره سفارش دهید.', 'muted'],
        default => null,
    };
    if ($msg) notify('m:' . $o['member_id'], 'order', (int) $o['id'], $msg[0], $msg[1], $msg[2], $link, $msg[3], $exp);
    // admin: only receipts waiting for review
    if ($o['status'] === 'reviewing') {
        $m = row('SELECT first_name, last_name FROM members WHERE id = ?', [$o['member_id']]);
        notify('admin', 'order', (int) $o['id'], '🧾', 'فیش جدید — سفارش ' . $code, ($m ? $m['first_name'] . ' ' . $m['last_name'] . ' · ' : '') . money((int) $o['total']) . ' تومان', 'admin/order-view.php?id=' . $o['id'], 'warn');
        shop_tg_admin("🧾 فیش جدید برای سفارش {$code}\n👤 " . ($m ? $m['first_name'] . ' ' . $m['last_name'] : '') . "\n💰 " . money((int) $o['total']) . " تومان\n🔗 " . site_url('admin/order-view.php?id=' . $o['id']));
    } else {
        unnotify('admin', 'order', (int) $o['id']);
    }
}

/** Short Telegram message to the shop admin (optional; never slows a request by more than a few seconds). */
function shop_tg_admin(string $text): void
{
    $chat = trim((string) setting('shop_tg_admin_chat'));
    $token = trim((string) setting('tg_bot_token'));
    if ($chat === '' || $token === '' || !function_exists('curl_init')) return;
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3]);
    @curl_exec($ch);
    curl_close($ch);
}

/* =========================================================================
   Notifications — one per (audience, thing): an order's notice is replaced as its status changes,
   finished ones vanish 24h later.
   ========================================================================= */

function notify(string $aud, string $type, int $id, string $icon, string $title, string $body, string $link, string $tone = 'info', ?string $expires = null): void
{
    q('INSERT INTO notifications (audience, ref_type, ref_id, icon, title, body, link, tone, is_read, expires_at, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
       ON DUPLICATE KEY UPDATE icon = VALUES(icon), title = VALUES(title), body = VALUES(body), link = VALUES(link), tone = VALUES(tone),
       is_read = 0, expires_at = VALUES(expires_at), updated_at = VALUES(updated_at)',
        [$aud, $type, $id, $icon, mb_substr($title, 0, 190), mb_substr($body, 0, 500), $link, $tone, $expires, now(), now()]);
}

function unnotify(string $aud, string $type, int $id): void
{
    q('DELETE FROM notifications WHERE audience = ? AND ref_type = ? AND ref_id = ?', [$aud, $type, $id]);
}

function notifs_cleanup(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    q('DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at < ?', [now()]);
}

function notifs_for(string $aud, int $limit = 20): array
{
    notifs_cleanup();
    return rows('SELECT * FROM notifications WHERE audience = ? ORDER BY updated_at DESC, id DESC LIMIT ' . (int) $limit, [$aud]);
}

function notifs_unread(string $aud): int
{
    notifs_cleanup();
    return (int) val('SELECT COUNT(*) FROM notifications WHERE audience = ? AND is_read = 0', [$aud]);
}

function notif_json(array $n): array
{
    $link = (string) $n['link'];
    return ['id' => (int) $n['id'], 'icon' => $n['icon'], 'title' => $n['title'], 'body' => (string) $n['body'], 'tone' => $n['tone'],
        'link' => $link !== '' ? url($link) : '', 'read' => (bool) $n['is_read'], 'ago' => jago($n['updated_at']), 'key' => $n['ref_type'] . $n['ref_id'] . strtotime($n['updated_at'])];
}

/* =========================================================================
   Private files (receipts, ticket attachments): stored outside public reach, served by PHP after a permission check
   ========================================================================= */

function private_dir(): string
{
    $dir = NX_ROOT . '/uploads/private';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function upload_private_image(array $file, string $sub): array
{
    if (!isset($file['error']) || is_array($file['error'])) return ['ok' => false, 'error' => 'فایل نامعتبر است.'];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'تصویر فیش را انتخاب کنید.'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'حجم تصویر زیاد است.' : 'خطا در آپلود (کد ' . $file['error'] . ')'];
    if ($file['size'] > 8 * 1024 * 1024) return ['ok' => false, 'error' => 'حداکثر حجم تصویر ۸ مگابایت است.'];
    $info = @getimagesize($file['tmp_name']);
    $mime = '';
    if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string) finfo_file($fi, $file['tmp_name']); finfo_close($fi); }
    if (!$info) return ['ok' => false, 'error' => 'فایل انتخابی تصویر نیست (عکس یا اسکرین‌شات فیش را انتخاب کنید).'];
    if ($mime === '') $mime = $info['mime'];
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($exts[$mime])) return ['ok' => false, 'error' => 'فقط تصاویر JPG، PNG یا WEBP.'];
    $rel = $sub . '/' . date('Y/m');
    $dir = private_dir() . '/' . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'پوشه uploads قابل نوشتن نیست.'];
    $name = bin2hex(random_bytes(16)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name) && !@rename($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
    if ($info[0] > 2200 && function_exists('imagecreatetruecolor') && $mime !== 'image/gif') resize_image($dir . '/' . $name, $mime, 2200);
    return ['ok' => true, 'path' => $rel . '/' . $name];
}

function serve_private(string $rel): never
{
    $base = realpath(private_dir());
    $file = realpath($base . '/' . $rel);
    if (!$file || !$base || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    exit;
}

/* =========================================================================
   Support tickets
   ========================================================================= */

function ticket_statuses(): array
{
    return [
        'open' => ['name' => 'در انتظار پاسخ', 'tone' => 'warn'],
        'answered' => ['name' => 'پاسخ داده شد', 'tone' => 'success'],
        'closed' => ['name' => 'بسته شد', 'tone' => 'muted'],
    ];
}

function ticket_add_message(array $t, string $sender, string $body, ?string $attachment = null, ?int $userId = null): void
{
    insert('ticket_messages', ['ticket_id' => (int) $t['id'], 'sender' => $sender, 'user_id' => $userId, 'body' => $body, 'attachment' => $attachment, 'created_at' => now()]);
    $status = $sender === 'admin' ? 'answered' : 'open';
    q('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $t['id']]);
    if ($sender === 'admin') {
        unnotify('admin', 'ticket', (int) $t['id']);
        notify('m:' . $t['member_id'], 'ticket', (int) $t['id'], '💬', 'پاسخ پشتیبانی: ' . $t['subject'], mb_substr($body, 0, 140), 'support/' . $t['code'], 'success');
    } else {
        $m = row('SELECT first_name, last_name FROM members WHERE id = ?', [$t['member_id']]);
        notify('admin', 'ticket', (int) $t['id'], '💬', 'پیام پشتیبانی — ' . $t['subject'], ($m ? $m['first_name'] . ' ' . $m['last_name'] . ': ' : '') . mb_substr($body, 0, 120), 'admin/ticket-view.php?id=' . $t['id'], 'info');
        shop_tg_admin("💬 پیام پشتیبانی — {$t['subject']}\n👤 " . ($m ? $m['first_name'] . ' ' . $m['last_name'] : '') . "\n\n" . mb_substr($body, 0, 400) . "\n🔗 " . site_url('admin/ticket-view.php?id=' . $t['id']));
    }
}

/** Where to go after signing in: back to the checkout / order / support page that asked for it. */
function after_login_url(bool $consume = true): string
{
    $to = (string) ($_SESSION['after_login'] ?? '');
    if ($consume) unset($_SESSION['after_login']);
    return preg_match('~^(checkout|orders|order/[A-Z0-9]+|support(/[A-Z0-9]+)?)$~i', $to) ? url($to) : url('account');
}

/* =========================================================================
   Site menus
   ========================================================================= */

function shop_menu_item(): array
{
    return ['title' => (string) setting('shop_menu_label', 'فروشگاه'), 'url' => '/shop', 'shop' => true];
}
