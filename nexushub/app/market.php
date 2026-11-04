<?php
/**
 * Live market board for the shop: dollar, euro, gold, Bitcoin and other coins — free public APIs, no key.
 * Prices are cached in the settings table (coins 3 min, currencies 6 h, failing sources retried after 3 min,
 * last good value kept for 48 h). Page views never wait on the network: the board is refreshed in the background.
 */

/** key => [name, short, icon, kind, nobitex symbol, coingecko id, kucoin symbol] */
function market_assets(): array
{
    return [
        'usd'    => ['دلار آمریکا', 'USD', '💵', 'fiat'],
        'eur'    => ['یورو', 'EUR', '💶', 'fiat'],
        'gbp'    => ['پوند انگلیس', 'GBP', '💷', 'fiat'],
        'aed'    => ['درهم امارات', 'AED', '🇦🇪', 'fiat'],
        'try'    => ['لیر ترکیه', 'TRY', '🇹🇷', 'fiat'],
        'gold18' => ['طلای ۱۸ عیار (گرم)', 'GOLD', '🪙', 'gold'],
        'ounce'  => ['انس جهانی طلا', 'XAU', '🥇', 'gold'],
        'btc'    => ['بیت‌کوین', 'BTC', '₿', 'coin', 'btc', 'bitcoin', 'BTC-USDT'],
        'eth'    => ['اتریوم', 'ETH', 'Ξ', 'coin', 'eth', 'ethereum', 'ETH-USDT'],
        'ton'    => ['تون‌کوین', 'TON', '💎', 'coin', 'ton', 'the-open-network', 'TON-USDT'],
        'usdt'   => ['تتر', 'USDT', '₮', 'fiat'],
        'sol'    => ['سولانا', 'SOL', '◎', 'coin', 'sol', 'solana', 'SOL-USDT'],
        'bnb'    => ['بایننس کوین', 'BNB', '🔶', 'coin', 'bnb', 'binancecoin', 'BNB-USDT'],
        'xrp'    => ['ریپل', 'XRP', '✕', 'coin', 'xrp', 'ripple', 'XRP-USDT'],
        'doge'   => ['دوج‌کوین', 'DOGE', '🐕', 'coin', 'doge', 'dogecoin', 'DOGE-USDT'],
        'trx'    => ['ترون', 'TRX', '🔺', 'coin', 'trx', 'tron', 'TRX-USDT'],
        'ada'    => ['کاردانو', 'ADA', '🔷', 'coin', 'ada', 'cardano', 'ADA-USDT'],
        'ltc'    => ['لایت‌کوین', 'LTC', 'Ł', 'coin', 'ltc', 'litecoin', 'LTC-USDT'],
        'not'    => ['نات‌کوین', 'NOT', '⚫', 'coin', 'not', 'notcoin', 'NOT-USDT'],
        'shib'   => ['شیبا', 'SHIB', '🐶', 'coin', 'shib', 'shiba-inu', 'SHIB-USDT'],
        'paxg'   => ['پکس‌گلد', 'PAXG', '🟨', 'coin', 'paxg', 'pax-gold', 'PAXG-USDT'],
    ];
}

function market_selected(): array
{
    $sel = array_values(array_intersect(array_filter(array_map('trim', explode(',', (string) setting('shop_tickers', 'usd,gold18,btc,eth')))), array_keys(market_assets())));
    return $sel;
}

/** coins in USD with 24h change %: [sym => [usd, change]] from Nobitex → CoinGecko → KuCoin */
function fetch_coins_usd(array $keys, ?array &$log = null, array $only = []): array
{
    $use = fn($n) => !$only || in_array($n, $only, true);
    $A = market_assets(); $want = [];
    foreach ($keys as $k) if (($A[$k][3] ?? '') === 'coin') $want[$k] = $A[$k];
    if (!$want) return [[], 'none'];
    $out = [];
    // 1) Nobitex (answers Iranian servers)
    $t = microtime(true);
    $j = !$use('Nobitex') ? null : http_json('https://api.nobitex.ir/market/stats?srcCurrency=' . implode(',', array_map(fn($a) => $a[4], $want)) . '&dstCurrency=usdt', 4);
    foreach ($want as $k => $a) {
        $s = $j['stats'][$a[4] . '-usdt'] ?? null;
        $v = (float) str_replace(',', '', (string) ($s['latest'] ?? 0));
        if ($v > 0 && empty($s['isClosed'])) $out[$k] = [$v, isset($s['dayChange']) ? (float) $s['dayChange'] : null];
    }
    if ($use('Nobitex')) $log[] = ['Nobitex', count($out) > 0, count($out), (int) round((microtime(true) - $t) * 1000)];
    if (count($out) === count($want)) return [$out, 'Nobitex'];
    $src = $out ? 'Nobitex' : '';
    // 2) CoinGecko for the rest
    $rest = array_diff_key($want, $out);
    $t = microtime(true);
    $j = !$use('CoinGecko') || !$rest ? null : http_json('https://api.coingecko.com/api/v3/simple/price?ids=' . implode(',', array_map(fn($a) => $a[5], $rest)) . '&vs_currencies=usd&include_24hr_change=true', 4);
    $n = 0;
    foreach ($rest as $k => $a) {
        $v = (float) ($j[$a[5]]['usd'] ?? 0);
        if ($v > 0) { $out[$k] = [$v, isset($j[$a[5]]['usd_24h_change']) ? (float) $j[$a[5]]['usd_24h_change'] : null]; $n++; }
    }
    if ($use('CoinGecko')) $log[] = ['CoinGecko', $n > 0, $n, (int) round((microtime(true) - $t) * 1000)];
    if ($n) $src = $src ? $src . ' + CoinGecko' : 'CoinGecko';
    if (count($out) === count($want)) return [$out, $src];
    // 3) KuCoin, one ticker each (only what is still missing)
    $rest = array_diff_key($want, $out); $n = 0; $t = microtime(true);
    foreach ($use('KuCoin') ? $rest : [] as $k => $a) {
        $j = http_json('https://api.kucoin.com/api/v1/market/stats?symbol=' . $a[6], 3);
        $v = (float) ($j['data']['last'] ?? 0);
        if ($v > 0) { $out[$k] = [$v, isset($j['data']['changeRate']) ? (float) $j['data']['changeRate'] * 100 : null]; $n++; }
        if (microtime(true) - $t > 6) break;
    }
    if ($use('KuCoin')) $log[] = ['KuCoin', $n > 0, $n, (int) round((microtime(true) - $t) * 1000)];
    if ($n) $src = $src ? $src . ' + KuCoin' : 'KuCoin';
    return [$out, $src ?: 'none'];
}

/** units per 1 USD (EUR, GBP, AED, TRY) from ECB-based free APIs */
function fetch_fiat_rates(?array &$log = null): array
{
    $sources = [
        'Frankfurter' => ['https://api.frankfurter.app/latest?from=USD&to=EUR,GBP,TRY', fn($j) => $j['rates'] ?? null],
        'ER-API' => ['https://open.er-api.com/v6/latest/USD', fn($j) => ($j['result'] ?? '') === 'success' ? ($j['rates'] ?? null) : null],
    ];
    foreach ($sources as $name => [$url, $pick]) {
        $t = microtime(true); $j = http_json($url, 4); $r = $j ? $pick($j) : null;
        $ok = is_array($r) && !empty($r['EUR']);
        $log[] = [$name, $ok, $ok ? count($r) : 0, (int) round((microtime(true) - $t) * 1000)];
        if ($ok) {
            $r = array_change_key_case(array_map('floatval', $r), CASE_UPPER);
            if (empty($r['AED'])) $r['AED'] = 3.6725;          // dirham is pegged to the dollar
            return [$r, $name];
        }
    }
    return [['AED' => 3.6725], 'none'];
}

/** Rows for the board: [key, name, short, icon, toman|null, usd|null, change|null] */
function market_board(bool $refresh = false, bool $force = false): array
{
    $sel = market_selected();
    if (!$sel) return ['items' => [], 'at' => null];
    $A = market_assets();
    $cache = json_decode((string) setting('shop_market_cache'), true) ?: [];
    $now = time(); $dirty = false;
    $needCoins = array_values(array_unique(array_merge(array_filter($sel, fn($k) => ($A[$k][3] ?? '') === 'coin'), array_intersect($sel, ['gold18', 'ounce']) ? ['paxg'] : [])));
    $needFiat = (bool) array_intersect($sel, ['eur', 'gbp', 'try', 'aed']);
    if ($force) {
        if ($now - (int) ($cache['forced_at'] ?? 0) < 20) $force = false;   // someone just refreshed: reuse it
        else { $cache['forced_at'] = $now; unset($cache['coins_fail'], $cache['fiat_fail']); $refresh = true; $dirty = true; }
    }
    if ($refresh) {
        $c = $cache['coins'] ?? null;
        $missing = $needCoins && (!$c || array_diff($needCoins, array_keys((array) ($c['v'] ?? []))));
        if ($needCoins && ($force || $missing || $now - (int) ($c['at'] ?? 0) > 180) && $now - (int) ($cache['coins_fail'] ?? 0) > 180) {
            $l = null; [$v, $src] = fetch_coins_usd($needCoins, $l);
            if ($v) { $cache['coins'] = ['v' => array_merge((array) ($c['v'] ?? []), $v), 'src' => $src, 'at' => $now]; unset($cache['coins_fail']); }
            else $cache['coins_fail'] = $now;
            $dirty = true;
        }
        $f = $cache['fiat'] ?? null;
        if ($needFiat && ($force || !$f || $now - (int) $f['at'] > 21600) && $now - (int) ($cache['fiat_fail'] ?? 0) > 180) {
            $l = null; [$v, $src] = fetch_fiat_rates($l);
            if ($src !== 'none') { $cache['fiat'] = ['v' => $v, 'src' => $src, 'at' => $now]; unset($cache['fiat_fail']); } else $cache['fiat_fail'] = $now;
            $dirty = true;
        }
        if ($dirty) save_settings(['shop_market_cache' => json_encode($cache)]);
    }
    $rates = shop_rates($force); $usdT = $rates['usd'] ?: null;
    $coins = ($cache['coins']['at'] ?? 0) > $now - 172800 ? (array) $cache['coins']['v'] : [];
    $fiat = ($cache['fiat']['at'] ?? 0) > $now - 172800 ? (array) $cache['fiat']['v'] : ['AED' => 3.6725];
    $items = [];
    foreach ($sel as $k) {
        [$name, $short, $icon, $kind] = $A[$k];
        $usd = null; $chg = null; $tm = null;
        if ($k === 'usd' || $k === 'usdt') { $tm = $usdT; $usd = 1.0; }
        elseif ($kind === 'fiat') { $per = (float) ($fiat[strtoupper($k)] ?? 0); if ($per > 0) { $usd = 1 / $per; $tm = $usdT ? $usdT / $per : null; } }
        elseif ($kind === 'gold') {
            $oz = $coins['paxg'][0] ?? null; $chg = $coins['paxg'][1] ?? null;
            if ($oz) { $usd = $k === 'ounce' ? $oz : $oz / 31.1035 * .75; $tm = $usdT ? $usd * $usdT : null; }
        } else { $usd = $coins[$k][0] ?? null; $chg = $coins[$k][1] ?? null; $tm = $usd && $usdT ? $usd * $usdT : null; }
        $items[] = ['k' => $k, 'name' => $name, 'short' => $short, 'icon' => $icon, 'toman' => $tm ? (int) round($tm) : null, 'usd' => $usd, 'chg' => $chg !== null ? round((float) $chg, 2) : null];
    }
    $at = max((int) ($cache['coins']['at'] ?? 0), (int) ($rates['usd_at'] ?? 0)) ?: null;
    return ['items' => $items, 'at' => $at, 'src' => trim(($cache['coins']['src'] ?? '') . ($needFiat && !empty($cache['fiat']['src']) ? ' · ' . $cache['fiat']['src'] : ''), ' ·')];
}

/** money style for the board: small values keep decimals */
function market_usd(?float $v): string
{
    if ($v === null) return '—';
    if ($v >= 1000) return number_format($v, 0);
    if ($v >= 1) return number_format($v, 2);
    if ($v >= .01) return number_format($v, 4);
    return rtrim(rtrim(number_format($v, 8), '0'), '.');
}
