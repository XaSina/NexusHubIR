<?php
/**
 * Prizes won on the site (raffles + lucky wheel) and the discount coupons some of them turn into.
 *
 *  prizes          one row per thing a member won (title, source, status, claim deadline)
 *  prize_messages  what the owner sent the winner about it (also pushed as a notification)
 *  coupons         a discount prize: applied by the shop itself at checkout, once
 *
 * Item prizes:     pending → delivered | expired (deadline passed — the prize is void)
 * Discount prizes: active  → used (an order used it) | expired (validity ended)
 */

/** normalise the discount settings an item/prize carries */
function discount_norm($d): ?array
{
    if (!is_array($d)) return null;
    $pct = max(0, min(100, round((float) ($d['pct'] ?? 0), 2)));
    if ($pct <= 0) return null;
    $scope = in_array($d['scope'] ?? '', ['cats', 'products'], true) ? $d['scope'] : 'all';
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['ids'] ?? [])))));
    if ($scope !== 'all' && !$ids) $scope = 'all';
    return ['pct' => $pct, 'max' => max(0, (int) ($d['max'] ?? 0)), 'min' => max(0, (int) ($d['min'] ?? 0)), 'scope' => $scope, 'ids' => $scope === 'all' ? [] : $ids,
        'days' => max(0, min(365, (int) ($d['days'] ?? 30)))];
}

/** "۱۰٪ تخفیف روی همه‌ی محصولات · حداقل خرید ۱۰۰٬۰۰۰ تومان · تا سقف ۵۰٬۰۰۰ تومان" */
function discount_text(array $d): string
{
    $s = fa_num((string) (float) $d['pct']) . '٪ تخفیف';
    if ($d['scope'] === 'cats') {
        $names = $d['ids'] ? array_column(rows('SELECT name FROM shop_categories WHERE id IN (' . implode(',', array_map('intval', $d['ids'])) . ')'), 'name') : [];
        $s .= ' روی ' . ($names ? implode('، ', $names) : 'دسته‌های انتخاب‌شده');
    } elseif ($d['scope'] === 'products') {
        $names = $d['ids'] ? array_column(rows('SELECT title FROM products WHERE id IN (' . implode(',', array_map('intval', $d['ids'])) . ')'), 'title') : [];
        $s .= ' روی ' . ($names ? implode('، ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' و …' : '') : 'محصولات انتخاب‌شده');
    } else $s .= ' روی همه‌ی محصولات';
    if ($d['min']) $s .= ' · حداقل خرید ' . money($d['min']) . ' تومان';
    if ($d['max']) $s .= ' · تا سقف ' . money($d['max']) . ' تومان';
    return $s;
}

function coupon_new_code(): string
{
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do { $c = 'GIFT-'; for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)]; } while (val('SELECT id FROM coupons WHERE code = ?', [$c]));
    return $c;
}

/**
 * Record a prize for a member. $disc (normalised) turns it into a coupon the shop applies by itself.
 * Sends the winner one notification that says what to do next.
 */
function prize_create(int $memberId, string $source, int $sourceId, string $title, string $emoji = '', ?array $disc = null, ?int $spinId = null, string $sourceTitle = '', string $link = ''): int
{
    $now = now();
    $pid = (int) insert('prizes', ['member_id' => $memberId, 'source' => $source, 'source_id' => $sourceId, 'spin_id' => $spinId, 'title' => mb_substr($title, 0, 190), 'emoji' => mb_substr($emoji, 0, 8),
        'prize_type' => $disc ? 'discount' : 'item', 'status' => $disc ? 'active' : 'pending',
        'deadline' => !$disc && prize_claim_on() ? date('Y-m-d H:i:s', prize_claim_deadline($now)) : null, 'created_at' => $now, 'updated_at' => $now]);
    $where = $sourceTitle !== '' ? ' — «' . $sourceTitle . '»' : '';
    if ($disc) {
        $exp = $disc['days'] ? date('Y-m-d H:i:s', time() + $disc['days'] * 86400) : null;
        $cid = (int) insert('coupons', ['code' => coupon_new_code(), 'member_id' => $memberId, 'prize_id' => $pid, 'pct' => $disc['pct'], 'max_off' => $disc['max'], 'min_total' => $disc['min'],
            'scope' => $disc['scope'], 'scope_ids' => implode(',', $disc['ids']), 'status' => 'active', 'expires_at' => $exp, 'created_at' => $now]);
        q('UPDATE prizes SET coupon_id = ?, deadline = ? WHERE id = ?', [$cid, $exp, $pid]);
        $code = (string) val('SELECT code FROM coupons WHERE id = ?', [$cid]);
        notify('m:' . $memberId, 'prize', $pid, '🏷️', 'کد تخفیف برات فعال شد: ' . $title, 'کد: ' . $code . ' — ' . discount_text($disc) . $where . '. موقع خرید از فروشگاه خودکار پیشنهاد می‌شه' . ($exp ? ' (تا ' . jdate('j F Y', strtotime($exp)) . ')' : '') . '.', 'prizes', 'success');
    } else {
        notify('m:' . $memberId, 'prize', $pid, '🏆', 'جایزه بردی: ' . $title, ltrim($where, ' —') . ($where ? '. ' : '') . prize_claim_text($now), 'prizes', 'success');
    }
    return $pid;
}

/** lazily close what ran out of time */
function prizes_expire_due(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        q("UPDATE prizes SET status = 'expired', updated_at = ? WHERE status = 'pending' AND deadline IS NOT NULL AND deadline < ?", [now(), now()]);
        q("UPDATE coupons SET status = 'expired' WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < ?", [now()]);
        q("UPDATE prizes p JOIN coupons c ON c.id = p.coupon_id SET p.status = c.status, p.updated_at = ? WHERE p.prize_type = 'discount' AND p.status <> c.status", [now()]);
    } catch (Throwable $e) {}
}

function prize_status_names(): array
{
    return ['pending' => ['⏳', 'منتظر دریافت', 'warn'], 'delivered' => ['✅', 'تحویل شد', 'ok'], 'expired' => ['⌛', 'باطل شد (وقت تموم شد)', 'muted'],
        'active' => ['🏷️', 'آماده‌ی استفاده', 'info'], 'used' => ['🛍️', 'استفاده شد', 'ok']];
}

/** the owner marks an item prize delivered (or takes it back) */
function prize_set_status(int $pid, string $status): void
{
    $p = row('SELECT * FROM prizes WHERE id = ?', [$pid]); if (!$p || $p['prize_type'] !== 'item') return;
    q('UPDATE prizes SET status = ?, delivered_at = ?, updated_at = ? WHERE id = ?', [$status, $status === 'delivered' ? now() : null, now(), $pid]);
    if ($p['spin_id']) q('UPDATE wheel_spins SET status = ? WHERE id = ?', [$status === 'delivered' ? 'delivered' : 'new', $p['spin_id']]);
    if ($status === 'delivered') notify('m:' . $p['member_id'], 'prize', $pid, '✅', 'جایزه‌ت تحویل داده شد: ' . $p['title'], 'برای جزئیات به «جایزه‌های من» سر بزن.', 'prizes', 'success');
}

/** a message from the owner to the winner about one prize */
function prize_message(int $pid, string $body): bool
{
    $p = row('SELECT * FROM prizes WHERE id = ?', [$pid]); $body = trim($body);
    if (!$p || $body === '') return false;
    insert('prize_messages', ['prize_id' => $pid, 'body' => mb_substr($body, 0, 2000), 'created_at' => now()]);
    q('UPDATE prizes SET updated_at = ? WHERE id = ?', [now(), $pid]);
    notify('m:' . $p['member_id'], 'prizemsg', $pid, '✉️', 'پیام مدیر درباره‌ی جایزه‌ت: ' . $p['title'], mb_substr($body, 0, 300), 'prizes', 'info');
    return true;
}

/* ---------- shop: discount coupons at checkout ---------- */

function coupon_matches(array $c, array $product): bool
{
    if ($c['scope'] === 'all') return true;
    $ids = array_map('intval', array_filter(explode(',', (string) $c['scope_ids'])));
    return $c['scope'] === 'cats' ? in_array((int) $product['category_id'], $ids, true) : in_array((int) $product['id'], $ids, true);
}

/** each active coupon of the member with what it would take off this cart */
function coupons_for_cart(array $member, array $lines): array
{
    prizes_expire_due();
    $out = [];
    foreach (rows("SELECT c.*, p.title prize_title FROM coupons c LEFT JOIN prizes p ON p.id = c.prize_id WHERE c.member_id = ? AND c.status = 'active' ORDER BY c.id", [$member['id']]) as $c) {
        $base = 0;
        foreach ($lines as $l) if (!$l['error'] && $l['total'] && coupon_matches($c, $l['product'])) $base += (int) $l['total'];
        $off = (int) floor($base * (float) $c['pct'] / 100);
        if ((int) $c['max_off'] > 0) $off = min($off, (int) $c['max_off']);
        $why = '';
        if (!$base) $why = 'روی محصولات این سبد اعمال نمی‌شه';
        elseif ((int) $c['min_total'] > 0 && $base < (int) $c['min_total']) $why = 'حداقل خرید ' . money((int) $c['min_total']) . ' تومانه';
        $out[] = ['c' => $c, 'base' => $base, 'off' => $why ? 0 : $off, 'why' => $why, 'text' => discount_text(['pct' => (float) $c['pct'], 'max' => (int) $c['max_off'], 'min' => (int) $c['min_total'], 'scope' => $c['scope'], 'ids' => array_filter(explode(',', (string) $c['scope_ids']))])];
    }
    return $out;
}

/** an order that ended without payment gives its coupon back */
function coupon_release(array $o): void
{
    if (empty($o['coupon_id'])) return;
    $c = row('SELECT * FROM coupons WHERE id = ?', [(int) $o['coupon_id']]);
    if (!$c || $c['status'] !== 'used' || (int) $c['order_id'] !== (int) $o['id']) return;
    $back = !$c['expires_at'] || strtotime($c['expires_at']) > time() ? 'active' : 'expired';
    q('UPDATE coupons SET status = ?, order_id = NULL, used_at = NULL WHERE id = ?', [$back, $c['id']]);
    if ($c['prize_id']) q('UPDATE prizes SET status = ?, updated_at = ? WHERE id = ?', [$back, now(), $c['prize_id']]);
}

/** member's prizes for the "my prizes" page */
function prizes_for_member(int $mid): array
{
    prizes_expire_due();
    $list = rows('SELECT p.*, c.code coupon_code, c.pct, c.max_off, c.min_total, c.scope, c.scope_ids, c.expires_at coupon_expires, c.order_id coupon_order
        FROM prizes p LEFT JOIN coupons c ON c.id = p.coupon_id WHERE p.member_id = ? ORDER BY p.id DESC LIMIT 500', [$mid]);
    $msgs = $list ? rows('SELECT * FROM prize_messages WHERE prize_id IN (' . implode(',', array_map(fn($p) => (int) $p['id'], $list)) . ') ORDER BY id', []) : [];
    foreach ($list as &$p) $p['messages'] = array_values(array_filter($msgs, fn($m) => (int) $m['prize_id'] === (int) $p['id']));
    unset($p);
    return $list;
}

/** where a prize came from, for lists */
function prize_source_label(array $p): string
{
    if ($p['source'] === 'gift') return '🎁 هدیه از طرف ' . setting('site_title');
    if ($p['source'] === 'raffle') { $t = val('SELECT title FROM raffles WHERE id = ?', [$p['source_id']]); return '🎰 قرعه‌کشی' . ($t ? ' «' . $t . '»' : ''); }
    $t = val('SELECT title FROM wheels WHERE id = ?', [$p['source_id']]); return '🎡 گردونه شانس' . ($t ? ' «' . $t . '»' : '');
}

/** v11: prizes for everything won before this table existed */
function prizes_backfill(): void
{
    if ((int) val('SELECT COUNT(*) FROM prizes')) return;
    foreach (rows("SELECT s.*, w.title wtitle FROM wheel_spins s JOIN wheels w ON w.id = s.wheel_id WHERE s.member_id IS NOT NULL AND s.kind = 'prize' ORDER BY s.id") as $s) {
        insert('prizes', ['member_id' => $s['member_id'], 'source' => 'wheel', 'source_id' => $s['wheel_id'], 'spin_id' => $s['id'], 'title' => $s['label'], 'emoji' => '', 'prize_type' => 'item',
            'status' => $s['status'] === 'delivered' ? 'delivered' : 'pending', 'deadline' => date('Y-m-d H:i:s', prize_claim_deadline($s['created_at'])), 'created_at' => $s['created_at'], 'updated_at' => now()]);
    }
    foreach (rows("SELECT * FROM raffles WHERE status = 'drawn' AND results IS NOT NULL") as $r) {
        foreach (json_decode((string) $r['results'], true) ?: [] as $w) {
            insert('prizes', ['member_id' => $w['member_id'], 'source' => 'raffle', 'source_id' => $r['id'], 'spin_id' => null, 'title' => $w['prize'], 'emoji' => '', 'prize_type' => 'item',
                'status' => 'pending', 'deadline' => date('Y-m-d H:i:s', prize_claim_deadline((string) $r['drawn_at'])), 'created_at' => $r['drawn_at'] ?: now(), 'updated_at' => now()]);
        }
    }
}

/** one prize as a card: what it is, its state, what to do (claim buttons / discount code) and the owner's messages */
function prize_card_html(array $p, bool $withSource = true): string
{
    $st = prize_status_names()[$p['status']] ?? ['•', $p['status'], 'muted'];
    $h = '<div class="pzc pzc-' . e($p['status']) . '">';
    $h .= '<div class="pzc-top"><span class="pzc-ic">' . e($p['emoji'] ?: ($p['prize_type'] === 'discount' ? '🏷️' : '🎁')) . '</span><div class="pzc-t"><b>' . e($p['title']) . '</b>'
        . ($withSource ? '<small>' . e(prize_source_label($p)) . ' · ' . e(jago($p['created_at'])) . '</small>' : '') . '</div><span class="pzc-st st-' . $st[2] . '">' . $st[0] . ' ' . e($st[1]) . '</span></div>';
    if ($p['prize_type'] === 'discount') {
        $d = ['pct' => (float) $p['pct'], 'max' => (int) $p['max_off'], 'min' => (int) $p['min_total'], 'scope' => $p['scope'], 'ids' => array_filter(explode(',', (string) $p['scope_ids']))];
        $h .= '<div class="pzc-disc"><span>' . e(discount_text($d)) . '</span><code dir="ltr">' . e((string) $p['coupon_code']) . '</code></div>';
        $h .= '<small class="pzc-note">' . ($p['status'] === 'active' ? '🛍️ موقع خرید از فروشگاه خودکار پیشنهاد و اعمال می‌شه' . ($p['coupon_expires'] ? ' — تا ' . e(jdate('j F Y', strtotime($p['coupon_expires']))) : '') . '.' : ($p['status'] === 'used' ? '✔️ روی یکی از سفارش‌هات استفاده شد.' : '⌛ مهلت استفاده تموم شد.')) . '</small>';
    } elseif ($p['status'] === 'pending' && $p['deadline']) {
        $left = strtotime($p['deadline']) - time();
        $h .= '<div class="pzc-claim"><small>⏳ ' . e(raffle_left_text(max(60, $left))) . ' وقت داری (تا ' . e(jdate('l j F — H:i', strtotime($p['deadline']))) . ') از تلگرام بهمون پیام بدی؛ بعدش جایزه باطل می‌شه.</small><div class="pc-ids">' . prize_claim_buttons() . '</div></div>';
    } elseif ($p['status'] === 'pending') {
        $h .= '<small class="pzc-note">به زودی برای تحویل جایزه باهات تماس می‌گیریم.</small>';
    }
    foreach ($p['messages'] ?? [] as $m) $h .= '<div class="pzc-msg"><span>✉️ پیام مدیر · ' . e(jago($m['created_at'])) . '</span><p>' . nl2br(e($m['body'])) . '</p></div>';
    return $h . '</div>';
}
