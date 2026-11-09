<?php
/**
 * Monthly sales report (Jalali months).
 * A month is "closed" automatically once it is over: its numbers are frozen into `sales_months`
 * and the panel gets a notification. The running month is always computed live.
 */

/** [jy, jm, jd] for now (site timezone) */
function jmonth_now(): array
{
    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y'), (int) date('n'), (int) date('j'));
    return [$jy, $jm, $jd];
}

function jmonth_prev(int $jy, int $jm): array { return $jm === 1 ? [$jy - 1, 12] : [$jy, $jm - 1]; }
function jmonth_next(int $jy, int $jm): array { return $jm === 12 ? [$jy + 1, 1] : [$jy, $jm + 1]; }
function jmonth_key(int $jy, int $jm): string { return sprintf('%04d-%02d', $jy, $jm); }

function jmonth_parse(string $key): ?array
{
    if (!preg_match('~^(\d{4})-(\d{2})$~', $key, $m)) return null;
    $jy = (int) $m[1]; $jm = (int) $m[2];
    return ($jm >= 1 && $jm <= 12 && $jy > 1300 && $jy < 1600) ? [$jy, $jm] : null;
}

function jmonth_label(int $jy, int $jm): string { return jalali_month_names()[$jm] . ' ' . fa_num((string) $jy); }

/** gregorian [start, end) datetimes of a Jalali month, and its number of days */
function jmonth_range(int $jy, int $jm): array
{
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, 1);
    [$ny, $nm] = jmonth_next($jy, $jm);
    [$hy, $hm, $hd] = jalali_to_gregorian($ny, $nm, 1);
    $s = sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd);
    $e = sprintf('%04d-%02d-%02d 00:00:00', $hy, $hm, $hd);
    $days = (int) round((strtotime($e) - strtotime($s)) / 86400);
    return [$s, $e, $days];
}

/** the moment an order counts as a sale */
const SALE_AT = 'COALESCE(o.approved_at, o.completed_at, o.paid_at, o.created_at)';
const SALE_OK = "o.status IN ('approved','completed')";

/** all numbers of one month */
function sales_month_stats(int $jy, int $jm): array
{
    [$s, $e, $days] = jmonth_range($jy, $jm);
    $sold = row('SELECT COUNT(*) AS n, COALESCE(SUM(o.total),0) AS rev, COALESCE(SUM(o.discount),0) AS dsc, COUNT(DISTINCT o.member_id) AS cust
        FROM orders o WHERE ' . SALE_OK . ' AND ' . SALE_AT . ' >= ? AND ' . SALE_AT . ' < ?', [$s, $e]) ?: [];
    $items = (int) val('SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE ' . SALE_OK . ' AND ' . SALE_AT . ' >= ? AND ' . SALE_AT . ' < ?', [$s, $e]);
    $orders = (int) val('SELECT COUNT(*) FROM orders o WHERE o.created_at >= ? AND o.created_at < ?', [$s, $e]);
    $lost = (int) val("SELECT COUNT(*) FROM orders o WHERE o.status IN ('canceled','expired','rejected') AND o.created_at >= ? AND o.created_at < ?", [$s, $e]);
    $members = 0;
    try { $members = (int) val('SELECT COUNT(*) FROM members WHERE created_at >= ? AND created_at < ?', [$s, $e]); } catch (Throwable $x) { }
    $top = [];
    foreach (rows('SELECT oi.title, SUM(oi.qty) AS q, SUM(oi.total) AS t FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE ' . SALE_OK . ' AND ' . SALE_AT . ' >= ? AND ' . SALE_AT . ' < ? GROUP BY oi.title ORDER BY t DESC, q DESC LIMIT 5', [$s, $e]) as $r)
        $top[] = [(string) $r['title'], (int) $r['q'], (int) $r['t']];
    $daily = array_fill(1, $days, 0);
    foreach (rows('SELECT DATE(' . SALE_AT . ') AS d, SUM(o.total) AS t FROM orders o WHERE ' . SALE_OK . ' AND ' . SALE_AT . ' >= ? AND ' . SALE_AT . ' < ? GROUP BY DATE(' . SALE_AT . ')', [$s, $e]) as $r) {
        $i = (int) floor((strtotime($r['d'] . ' 00:00:00') - strtotime($s)) / 86400) + 1;
        if ($i >= 1 && $i <= $days) $daily[$i] += (int) $r['t'];
    }
    $n = (int) ($sold['n'] ?? 0); $rev = (int) ($sold['rev'] ?? 0);
    return [
        'key' => jmonth_key($jy, $jm), 'days' => $days,
        'revenue' => $rev, 'sold' => $n, 'orders' => $orders, 'lost' => $lost,
        'discount' => (int) ($sold['dsc'] ?? 0), 'customers' => (int) ($sold['cust'] ?? 0), 'items' => $items, 'members' => $members,
        'avg' => $n ? (int) round($rev / $n) : 0, 'conv' => $orders ? round($n * 100 / $orders, 1) : 0.0,
        'top' => $top, 'daily' => array_values($daily),
    ];
}

/** closes every finished month that isn't stored yet (cheap no-op when already up to date) */
function sales_close_months(bool $rebuild = false): int
{
    [$cy, $cm] = jmonth_now();
    [$py, $pm] = jmonth_prev($cy, $cm);
    $prevKey = jmonth_key($py, $pm);
    if (!$rebuild && (string) setting('sales_closed_upto') === $prevKey) return 0;
    try {
        $first = val('SELECT MIN(created_at) FROM orders');
    } catch (Throwable $e) { return 0; }
    if (!$first) { save_settings(['sales_closed_upto' => $prevKey]); return 0; }
    $t = strtotime((string) $first);
    [$jy, $jm] = gregorian_to_jalali((int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
    $done = $rebuild ? [] : array_flip(array_column(rows('SELECT ym FROM sales_months'), 'ym'));
    $closed = 0; $last = null; $guard = 0;
    while (jmonth_key($jy, $jm) < jmonth_key($cy, $cm) && $guard++ < 600) {
        $k = jmonth_key($jy, $jm);
        if (!isset($done[$k])) {
            $st = sales_month_stats($jy, $jm);
            q('REPLACE INTO sales_months (ym, data, closed_at) VALUES (?, ?, ?)', [$k, json_encode($st, JSON_UNESCAPED_UNICODE), now()]);
            $closed++; $last = [$jy, $jm, $st];
        }
        [$jy, $jm] = jmonth_next($jy, $jm);
    }
    save_settings(['sales_closed_upto' => $prevKey]);
    // a short note in the panel bell when last month gets closed on its own
    if (!$rebuild && $last && jmonth_key($last[0], $last[1]) === $prevKey && function_exists('notify')) {
        try {
            notify('admin', 'sales_month', (int) ($last[0] * 100 + $last[1]), '📈', 'گزارش ' . jmonth_label($last[0], $last[1]) . ' بسته شد',
                'فروش ماه: ' . money($last[2]['revenue']) . ' تومان · ' . fa_num((string) $last[2]['sold']) . ' سفارش موفق', 'admin/reports.php?m=' . $prevKey, 'success');
        } catch (Throwable $e) { }
    }
    return $closed;
}

/** stats of any month: frozen copy for closed months, live numbers for the running one */
function sales_month(int $jy, int $jm): array
{
    static $cache = [];
    $k = jmonth_key($jy, $jm);
    if (isset($cache[$k])) return $cache[$k];
    return $cache[$k] = sales_month_load($jy, $jm, $k);
}

function sales_month_load(int $jy, int $jm, string $k): array
{
    [$cy, $cm] = jmonth_now();
    if ($k < jmonth_key($cy, $cm)) {
        $r = row('SELECT data, closed_at FROM sales_months WHERE ym = ?', [$k]);
        if ($r && is_array($d = json_decode((string) $r['data'], true))) return $d + ['closed_at' => $r['closed_at']];
    }
    return sales_month_stats($jy, $jm) + ['closed_at' => null];
}

/** percent change; null when there is nothing to compare with */
function sales_delta($now, $before): ?float
{
    $now = (float) $now; $before = (float) $before;
    if ($before == 0.0) return $now == 0.0 ? 0.0 : null;
    return round(($now - $before) * 100 / $before, 1);
}
