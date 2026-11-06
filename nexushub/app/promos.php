<?php
/**
 * Public discount codes (کد تخفیف همگانی): the owner makes a code, anyone can type it in the cart.
 * A usage limit (how many people) is enforced but never shown to visitors; each member uses a code once.
 * Personal codes sent to members are coupons (see prizes.php, source 'gift').
 */

function promo_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    return $code === '' ? null : (row('SELECT * FROM promo_codes WHERE code = ?', [$code]) ?: null);
}

function promo_disc(array $p): array
{
    return ['pct' => (float) $p['pct'], 'max' => (int) $p['max_off'], 'min' => (int) $p['min_total'], 'scope' => $p['scope'], 'ids' => array_filter(array_map('intval', explode(',', (string) $p['scope_ids'])))];
}

/** can this member (or a guest) use the code right now? returns '' or the reason */
function promo_problem(array $p, ?array $m): string
{
    if (!$p['active']) return 'این کد تخفیف فعال نیست.';
    if ($p['starts_at'] && strtotime($p['starts_at']) > time()) return 'این کد هنوز شروع نشده.';
    if ($p['expires_at'] && strtotime($p['expires_at']) < time()) return 'مهلت این کد تخفیف تموم شده.';
    if ((int) $p['max_uses'] > 0 && (int) $p['uses'] >= (int) $p['max_uses']) return 'ظرفیت این کد تخفیف پر شده.';
    if ($m && val('SELECT 1 FROM promo_uses WHERE promo_id = ? AND member_id = ?', [$p['id'], $m['id']])) return 'قبلاً از این کد استفاده کردی.';
    return '';
}

/** what the code takes off this cart: ['off' => int, 'why' => string] */
function promo_for_cart(array $p, array $lines): array
{
    $c = ['scope' => $p['scope'], 'scope_ids' => $p['scope_ids']];
    $base = 0;
    foreach ($lines as $l) if (!$l['error'] && $l['total'] && coupon_matches($c, $l['product'])) $base += (int) $l['total'];
    $off = (int) floor($base * (float) $p['pct'] / 100);
    if ((int) $p['max_off'] > 0) $off = min($off, (int) $p['max_off']);
    $why = !$base ? 'روی محصولات این سبد اعمال نمی‌شه' : ((int) $p['min_total'] > 0 && $base < (int) $p['min_total'] ? 'حداقل خرید ' . money((int) $p['min_total']) . ' تومانه' : '');
    return ['off' => $why ? 0 : $off, 'why' => $why, 'base' => $base];
}

/** the code typed in the cart (kept in the session) */
function promo_session(): ?array
{
    $code = (string) ($_SESSION['promo_code'] ?? '');
    return $code !== '' ? promo_find($code) : null;
}

/** an order that ended without payment gives the use back */
function promo_release(array $o): void
{
    if (empty($o['promo_id'])) return;
    $n = q('DELETE FROM promo_uses WHERE promo_id = ? AND order_id = ?', [(int) $o['promo_id'], (int) $o['id']])->rowCount();
    if ($n) q('UPDATE promo_codes SET uses = GREATEST(uses - 1, 0) WHERE id = ?', [(int) $o['promo_id']]);
}

/** promo box for the ad slots (code + copy) */
function promo_box_html(array $p, bool $preview = false): string
{
    $theme = array_key_exists((string) ($p['box_theme'] ?? ''), ad_themes()) ? (string) $p['box_theme'] : 'neon';
    $accent = valid_hex((string) ($p['box_accent'] ?? ''), '') ?: valid_hex((string) setting('color_primary', '#ff1f3d'), '#ff1f3d');
    $title = e(($p['title'] ?? '') ?: 'کد تخفیف ویژه');
    $left = $p['expires_at'] ? strtotime($p['expires_at']) - time() : 0;
    return '<div class="nxp nxp-theme-' . $theme . ' rfx rfx-promo" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . '">'
        . ad_deco($theme)
        . '<div class="nxp-inner rfx-in">'
        . '<span class="rfx-art" aria-hidden="true"><span class="rfx-ring"></span><span class="rfx-emoji">🏷️</span></span>'
        . '<span class="rfx-body"><span class="rfx-tag"><i class="rfx-dot"></i> کد تخفیف</span>'
        . '<strong class="nxp-title rfx-title"' . ($theme === 'glitch' ? ' data-text="' . $title . '"' : '') . '>' . $title . '</strong>'
        . '<span class="rfx-prize">' . e(discount_text(promo_disc($p))) . '</span>'
        . ($left > 0 ? '<span class="rfx-how">⏳ تا ' . e(jdate('j F — H:i', strtotime($p['expires_at']))) . '</span>' : '') . '</span>'
        . '<span class="rfx-side"><button type="button" class="pmx-code" data-copy="' . e($p['code']) . '" data-copy-msg="کد تخفیف کپی شد ✅"><code dir="ltr">' . e($p['code']) . '</code><span>📋 کپی</span></button>'
        . (shop_on() && !$preview ? '<a class="rfx-cta" href="' . e(url('shop')) . '">🛍️ بریم خرید ' . icon('arrow-left', 15) . '</a>' : '') . '</span>'
        . '</div></div>';
}

function promo_slot(string $position): string
{
    static $list = null;
    if ($list === null) {
        $list = [];
        try { $list = array_values(array_filter(rows('SELECT * FROM promo_codes WHERE active = 1 AND box_on = 1 ORDER BY id DESC LIMIT 20'), fn($p) => promo_problem($p, null) === '')); } catch (Throwable $e) {}
    }
    $out = '';
    foreach ($list as $p) {
        if (!in_array($position, array_filter(array_map('trim', explode(',', (string) $p['box_places']))), true)) continue;
        if ($position === 'header_top') { $out .= top_strip('p' . $p['id'], '🏷️', (($p['title'] ?? '') ?: 'کد تخفیف ویژه') . ' — ' . discount_text(promo_disc($p)), '', '', (string) ($p['box_accent'] ?? ''), (string) $p['code']); continue; }
        $out .= '<div class="nxp-slot nxp-pos-' . e($position) . ' raffle-promo" data-reveal>' . promo_box_html($p) . '</div>';
    }
    return $out;
}

/** members who buy the most (approved / completed orders) */
function top_buyers(int $limit = 30, int $days = 0): array
{
    $since = $days ? date('Y-m-d H:i:s', time() - $days * 86400) : '1970-01-01 00:00:00';
    return rows("SELECT m.id, m.first_name, m.last_name, m.phone, m.member_code, COUNT(o.id) orders, SUM(o.total) spent, MAX(o.created_at) last_at
        FROM orders o JOIN members m ON m.id = o.member_id WHERE o.status IN ('approved','completed') AND o.created_at >= ?
        GROUP BY m.id ORDER BY spent DESC LIMIT " . max(1, min(200, $limit)), [$since]);
}
