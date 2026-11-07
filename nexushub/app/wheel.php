<?php
/**
 * Lucky wheel (گردونه شانس) — separate from raffles.
 *  - audience 'private': only the owner spins it, inside the panel (a quick picker / private draw)
 *  - audience 'public' : members spin it on the site; spins are earned by rules (sign-up, every N purchases,
 *    every N invites, a daily free spin) or granted by the owner.
 * Each item has an exact chance (percent). The server picks with a CSPRNG, the wheel only animates to it.
 */

function wheel_items(array $w): array
{
    $items = json_decode((string) ($w['items'] ?? ''), true);
    $out = [];
    foreach (is_array($items) ? $items : [] as $it) {
        $label = trim((string) ($it['label'] ?? ''));
        if ($label === '') continue;
        $out[] = ['label' => mb_substr($label, 0, 60), 'emoji' => mb_substr((string) ($it['emoji'] ?? ''), 0, 4), 'chance' => max(0, min(100, round((float) ($it['chance'] ?? 0), 3))),
            'kind' => in_array($it['kind'] ?? '', ['empty', 'again', 'discount'], true) ? $it['kind'] : 'prize', 'code' => mb_substr((string) ($it['code'] ?? ''), 0, 120), 'stock' => (int) ($it['stock'] ?? -1),
            'disc' => discount_norm($it['disc'] ?? null)];
        $k = array_key_last($out); if ($out[$k]['kind'] === 'discount' && !$out[$k]['disc']) $out[$k]['disc'] = discount_norm(['pct' => 10]);
    }
    return $out;
}

function wheel_rules(array $w): array
{
    $r = json_decode((string) ($w['rules'] ?? ''), true);
    $r = is_array($r) ? $r : [];
    $d = ['signup' => 1, 'buy_every' => 0, 'buy_spins' => 1, 'invite_every' => 0, 'invite_spins' => 1, 'daily' => 0, 'max_user' => 0, 'old_buys' => 0];
    foreach ($d as $k => $v) $d[$k] = max(0, (int) ($r[$k] ?? $v));
    return $d;
}

function wheel_url(array $w): string { return url('wheel/' . rawurlencode((string) $w['slug'])); }

function wheel_open(array $w): bool
{
    return $w['audience'] === 'public' && $w['status'] === 'active' && (empty($w['end_at']) || strtotime((string) $w['end_at']) > time());
}

/** what a member has earned / used / has left */
function wheel_member(array $w, ?array $m): ?array
{
    if (!$m) return null;
    $wid = (int) $w['id']; $mid = (int) $m['id']; $R = wheel_rules($w);
    $since = $R['old_buys'] ? '1970-01-01 00:00:00' : (string) $w['created_at'];
    $buys = $R['buy_every'] ? (int) val("SELECT COUNT(*) FROM orders WHERE member_id = ? AND status IN ('approved','completed') AND created_at >= ?", [$mid, $since]) : 0;
    $invites = $R['invite_every'] ? (int) val('SELECT COUNT(*) FROM wheel_refs WHERE wheel_id = ? AND ref_by = ?', [$wid, $mid]) : 0;
    $grants = (int) val('SELECT COALESCE(SUM(spins), 0) FROM wheel_grants WHERE wheel_id = ? AND member_id = ?', [$wid, $mid]);
    $earned = $R['signup'] + ($R['buy_every'] ? intdiv($buys, $R['buy_every']) * $R['buy_spins'] : 0) + ($R['invite_every'] ? intdiv($invites, $R['invite_every']) * $R['invite_spins'] : 0) + $grants;
    $usedEarned = (int) val("SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id = ? AND src = 'earned'", [$wid, $mid]);
    $usedToday = (int) val("SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id = ? AND src = 'daily' AND created_at >= ?", [$wid, $mid, date('Y-m-d 00:00:00')]);
    $usedAll = (int) val('SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id = ?', [$wid, $mid]);
    $leftEarned = max(0, $earned - $usedEarned);
    $leftDaily = max(0, $R['daily'] - $usedToday);
    $left = $leftEarned + $leftDaily;
    if ($R['max_user']) $left = max(0, min($left, $R['max_user'] - $usedAll));
    return ['left' => $left, 'left_daily' => min($left, $leftDaily), 'earned' => $earned, 'used' => $usedAll, 'buys' => $buys, 'invites' => $invites, 'grants' => $grants,
        'buy_next' => $R['buy_every'] ? $R['buy_every'] - $buys % $R['buy_every'] : 0, 'invite_next' => $R['invite_every'] ? $R['invite_every'] - $invites % $R['invite_every'] : 0,
        'capped' => $R['max_user'] && $usedAll >= $R['max_user'],
        'invite' => invite_link($m, 'w' . (int) $w['id'])];
}

/** weighted pick with a CSPRNG; items with no stock left drop out and the rest share their chance */
function wheel_pick(array $items): int
{
    $pool = [];
    foreach ($items as $i => $it) if ($it['chance'] > 0 && $it['stock'] !== 0) $pool[$i] = (int) round($it['chance'] * 1000);
    if (!$pool) return -1;
    $total = array_sum($pool); $roll = random_int(1, $total); $acc = 0;
    foreach ($pool as $i => $wgt) { $acc += $wgt; if ($roll <= $acc) return $i; }
    return (int) array_key_last($pool);
}

/** one spin. $m = member (site) or null for the owner spinning in the panel */
function wheel_spin(array $w, ?array $m): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $w = row('SELECT * FROM wheels WHERE id = ? FOR UPDATE', [(int) $w['id']]);
        if (!$w) throw new RuntimeException('گردونه پیدا نشد.');
        $src = 'admin';
        if ($m) {
            if (!wheel_open($w)) throw new RuntimeException('این گردونه الان فعال نیست.');
            $me = wheel_member($w, $m);
            if ($me['left'] <= 0) throw new RuntimeException($me['capped'] ? 'به سقف چرخش‌های این گردونه رسیدی.' : 'فعلاً چرخش نداری؛ راه‌های گرفتن چرخش رو پایین صفحه ببین.');
            $src = $me['left_daily'] > 0 ? 'daily' : 'earned';
        }
        $items = wheel_items($w);
        $i = wheel_pick($items);
        if ($i < 0) throw new RuntimeException('هیچ گزینه‌ای با شانس بیشتر از صفر (و موجودی) نداره.');
        $it = $items[$i];
        if ($m && $it['stock'] > 0) {   // limited prize: one less left
            $items[$i]['stock']--;
            q('UPDATE wheels SET items = ?, updated_at = ? WHERE id = ?', [json_encode($items, JSON_UNESCAPED_UNICODE), now(), $w['id']]);
        }
        $won = in_array($it['kind'], ['prize', 'discount'], true);
        $sid = (int) insert('wheel_spins', ['wheel_id' => $w['id'], 'member_id' => $m ? (int) $m['id'] : null, 'item' => $i, 'label' => $it['label'], 'kind' => $won ? 'prize' : $it['kind'],
            'src' => $src, 'status' => $won && $m ? 'new' : 'done', 'created_at' => now()]);
        if ($m && $it['kind'] === 'again') insert('wheel_grants', ['wheel_id' => $w['id'], 'member_id' => (int) $m['id'], 'spins' => 1, 'note' => 'چرخش دوباره از خود گردونه', 'created_at' => now()]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e instanceof RuntimeException ? $e->getMessage() : 'خطا در چرخش؛ دوباره امتحان کن.'];
    }
    $coupon = null;
    if ($m && in_array($it['kind'], ['prize', 'discount'], true)) {
        $pid = prize_create((int) $m['id'], 'wheel', (int) $w['id'], $it['label'], $it['emoji'], $it['kind'] === 'discount' ? $it['disc'] : null, $sid, (string) $w['title']);
        if ($it['kind'] === 'discount') $coupon = ['code' => (string) val('SELECT c.code FROM coupons c JOIN prizes p ON p.coupon_id = c.id WHERE p.id = ?', [$pid]), 'text' => discount_text($it['disc'])];
        notify('admin', 'wheel', (int) $w['id'], '🎡', 'برنده‌ی گردونه: ' . $it['label'], member_full_name($m) . ' — «' . $w['title'] . '»', 'admin/wheel-view.php?id=' . $w['id'], 'success');
    }
    return ['ok' => true, 'index' => $i, 'spin' => $sid, 'item' => ['label' => $it['label'], 'emoji' => $it['emoji'], 'kind' => $it['kind'], 'code' => $it['code'], 'coupon' => $coupon,
        'disc' => $it['kind'] === 'discount' ? discount_text($it['disc']) : '']];
}

/** remember who invited a member (only members who signed up after the wheel was made count) */
function wheel_track_ref(array $w, ?array $m): void
{
    $code = (string) ($_SESSION['wheel_ref'][(int) $w['id']] ?? '');
    if ($m && $code === '' && !empty($m['invited_by'])) $code = (string) val('SELECT COALESCE(invite_code, member_code) FROM members WHERE id = ?', [(int) $m['invited_by']]);
    if (!$m || $code === '' || $code === $m['member_code'] || $code === ($m['invite_code'] ?? '')) return;
    if (strtotime((string) $m['created_at']) < strtotime((string) $w['created_at'])) return;
    $ref = invite_owner($code);
    if (!$ref || (int) $ref['id'] === (int) $m['id']) return;
    try { q('INSERT IGNORE INTO wheel_refs (wheel_id, member_id, ref_by, created_at) VALUES (?, ?, ?, ?)', [$w['id'], $m['id'], $ref['id'], now()]); } catch (Throwable $e) {}
    unset($_SESSION['wheel_ref'][(int) $w['id']]);
}

function wheel_first_name(?string $first): string { return trim((string) $first) ?: 'کاربر'; }

/** everything the wheel page needs */
function wheel_state(array $w, ?array $me, bool $admin = false): array
{
    $items = wheel_items($w);
    $show = $admin || !empty($w['show_chances']);
    $total = array_sum(array_map(fn($it) => $it['stock'] !== 0 ? $it['chance'] : 0, $items)) ?: 1;
    $recent = [];
    if ($me) prizes_expire_due();
    $mine = $me ? rows('SELECT s.label, s.kind, s.item, s.created_at, p.status pstatus, p.prize_type, p.deadline, c.code FROM wheel_spins s LEFT JOIN prizes p ON p.spin_id = s.id LEFT JOIN coupons c ON c.id = p.coupon_id
        WHERE s.wheel_id = ? AND s.member_id = ? ORDER BY s.id DESC LIMIT 12', [$w['id'], $me['id']]) : [];
    return [
        'id' => (int) $w['id'], 'admin' => $admin, 'open' => wheel_open($w), 'audience' => $w['audience'], 'status' => $w['status'],
        'items' => array_map(fn($it) => ['label' => $it['label'], 'emoji' => $it['emoji'], 'kind' => $it['kind'], 'w' => 1,
            'chance' => $show ? round($it['stock'] !== 0 ? $it['chance'] / $total * 100 : 0, 2) : null, 'out' => $it['stock'] === 0], $items),
        'me' => wheel_member($w, $me),
        'mine' => array_map(fn($x) => ['label' => $x['label'], 'kind' => $x['prize_type'] === 'discount' ? 'discount' : $x['kind'], 'when' => jago($x['created_at']), 'status' => $x['pstatus'],
            'code' => $x['code'], 'left' => $x['pstatus'] === 'pending' && $x['deadline'] ? strtotime($x['deadline']) - time() : null], $mine),
        'claim' => prize_claim_on() ? ['hours' => prize_claim_hours(), 'buttons' => prize_claim_buttons(), 'prizes' => url('prizes')] : null,
        'recent' => array_map(fn($x) => ['name' => $admin ? trim($x['first_name'] . ' ' . $x['last_name']) : wheel_first_name($x['first_name']), 'label' => $x['label'], 'when' => jago($x['created_at'])], $recent),
        'rules' => wheel_rules($w), 'logged' => (bool) $me,
    ];
}

/** promo box for the site (same design family as the raffle box) */
function wheel_box_html(array $w, bool $preview = false): string
{
    $theme = array_key_exists((string) ($w['box_theme'] ?? ''), ad_themes()) ? (string) $w['box_theme'] : 'neon';
    $accent = valid_hex((string) ($w['box_accent'] ?? ''), '') ?: valid_hex((string) setting('color_primary', '#ff1f3d'), '#ff1f3d');
    $items = array_values(array_filter(wheel_items($w), fn($it) => $it['kind'] === 'prize'));
    $R = wheel_rules($w);
    $prize = $items ? implode('، ', array_slice(array_column($items, 'label'), 0, 2)) : 'جایزه‌های ویژه';
    $more = count($items) > 2 ? ' + ' . fa_num((string) (count($items) - 2)) . ' جایزه' : '';
    $how = $R['signup'] ? '🎁 ثبت‌نام = ' . fa_num((string) $R['signup']) . ' چرخش رایگان' : ($R['daily'] ? '☀️ هر روز ' . fa_num((string) $R['daily']) . ' چرخش رایگان' : ($R['buy_every'] ? '🛍️ هر ' . fa_num((string) $R['buy_every']) . ' خرید = چرخش' : '🎡 بچرخون و جایزه ببر'));
    $img = !empty($w['image']) ? media_url((string) $w['image']) : '';
    $title = e(($w['title'] ?? '') ?: 'گردونه شانس');
    return '<div class="nxp nxp-theme-' . $theme . ' rfx rfx-wheel" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . ';--nxp-on:' . nxp_on($accent) . '">'
        . ad_deco($theme) . ($theme === 'ribbon' ? '<span class="nxp-ribbon">ویژه</span>' : '')
        . '<a class="nxp-inner rfx-in" href="' . e($preview ? '#' : wheel_url($w)) . '">'
        . '<span class="rfx-art rfx-art-wheel" aria-hidden="true"><span class="rfx-ring"></span>' . ($img ? '<img src="' . e($img) . '" alt="" loading="lazy">' : '<span class="rfx-mini"></span>') . '</span>'
        . '<span class="rfx-body"><span class="rfx-tag"><i class="rfx-dot"></i> گردونه شانس</span>'
        . '<strong class="nxp-title rfx-title"' . ($theme === 'glitch' ? ' data-text="' . $title . '"' : '') . '>' . $title . '</strong>'
        . '<span class="rfx-prize">🎁 ' . e($prize) . '<em>' . e($more) . '</em></span><span class="rfx-how">' . e($how) . '</span></span>'
        . '<span class="rfx-side"><span class="rfx-cta">🎡 بچرخون ' . icon('arrow-left', 15) . '</span></span>'
        . '</a></div>';
}

function wheel_slot(string $position): string
{
    if (!setting_on('footer_wheel_link')) return '';
    static $list = null;
    if ($list === null) {
        $list = [];
        try { $list = array_values(array_filter(rows("SELECT * FROM wheels WHERE audience = 'public' AND status = 'active' AND box_on = 1 ORDER BY id DESC"), 'wheel_open')); } catch (Throwable $e) {}
    }
    $out = '';
    foreach ($list as $w) {
        if (!in_array($position, array_filter(array_map('trim', explode(',', (string) $w['box_places']))), true)) continue;
        if ($position === 'header_top') { $out .= top_strip('w' . $w['id'], '🎡', $w['title'] . ($w['description'] ? ' — ' . strip_tags((string) $w['description']) : ''), wheel_url($w), 'بچرخون', (string) ($w['box_accent'] ?? '')); continue; }
        $out .= '<div class="nxp-slot nxp-pos-' . e($position) . ' raffle-promo" data-reveal>' . wheel_box_html($w) . '</div>';
    }
    return $out;
}

/** a ready-made set of items to start from */
function wheel_sample_items(): array
{
    return [
        ['label' => 'پوچ', 'emoji' => '😅', 'chance' => 35, 'kind' => 'empty', 'code' => '', 'stock' => -1],
        ['label' => '۱۰٪ تخفیف', 'emoji' => '🏷️', 'chance' => 25, 'kind' => 'discount', 'code' => '', 'stock' => -1, 'disc' => ['pct' => 10, 'max' => 0, 'min' => 0, 'scope' => 'all', 'ids' => [], 'days' => 30]],
        ['label' => 'یه چرخش دیگه', 'emoji' => '🔁', 'chance' => 10, 'kind' => 'again', 'code' => '', 'stock' => -1],
        ['label' => '۵۰ استارز', 'emoji' => '⭐', 'chance' => 15, 'kind' => 'prize', 'code' => '', 'stock' => -1],
        ['label' => '۲۰٪ تخفیف', 'emoji' => '💸', 'chance' => 10, 'kind' => 'discount', 'code' => '', 'stock' => -1, 'disc' => ['pct' => 20, 'max' => 0, 'min' => 0, 'scope' => 'all', 'ids' => [], 'days' => 30]],
        ['label' => 'پریمیوم ۳ ماهه', 'emoji' => '💎', 'chance' => 5, 'kind' => 'prize', 'code' => '', 'stock' => 3],
    ];
}
