<?php
/**
 * NEXUS | HUB — raffles (قرعه‌کشی)
 *  modes:  free      every member joins once, equal chance
 *          referral  free + every friend who joins with your link gives you a bonus ticket (+X% of a normal chance)
 *          paid      members buy numbered tickets (card-to-card + receipt, approved in the panel); a ticket number wins
 *  The draw runs on the server the first time anyone (or the admin) touches the raffle after its time; the page then
 *  spins the wheel to the stored result. If the minimum participants / tickets are not reached, it is not held.
 */

function raffle_modes(): array
{
    return [
        'free' => ['رایگان', 'هر عضو یک بار شرکت می‌کنه؛ شانس همه برابره', '🎟️'],
        'referral' => ['رایگان + دعوت دوستان', 'هر دوستی که با لینک تو شرکت کنه یک تیکت اضافه بهت می‌ده', '🤝'],
        'paid' => ['خرید تیکت', 'هر نفر تیکت شماره‌دار می‌خره؛ یک شماره برنده می‌شه', '💳'],
    ];
}

function raffle_status_names(): array
{
    return ['draft' => 'پیش‌نویس', 'active' => 'در حال ثبت‌نام', 'drawn' => 'برگزار شد', 'failed' => 'برگزار نشد', 'cancelled' => 'لغو شد'];
}

function raffle_prizes(array $r): array
{
    $p = json_decode((string) ($r['prizes'] ?? ''), true);
    $p = is_array($p) ? array_values(array_filter($p, fn($x) => is_array($x) && trim((string) ($x['title'] ?? '')) !== '')) : [];
    return $p ?: [['title' => 'جایزه', 'emoji' => '🎁']];
}

function raffle_url(array $r): string
{
    return url('raffle/' . rawurlencode($r['slug']));
}

function raffle_public_name(array $m): string
{
    // visitors only ever see the first name; the full name stays in the admin panel
    return trim((string) ($m['first_name'] ?? '')) ?: 'کاربر';
}

/**
 * Who may open a raffle's page. Running raffles and the result window are open to everyone;
 * after that the owner decides (setting raffle_ended_access): all / participants / winners only.
 */
function raffle_page_allowed(array $r, ?array $me): bool
{
    if (current_user()) return true;
    if (in_array($r['status'], ['active', 'cancelled'], true)) return true;
    if ($r['status'] === 'drawn' && raffle_result_live($r)) return true;
    $rule = (string) setting('raffle_ended_access', 'winners');
    if ($rule === 'all') return true;
    if (!$me) return false;
    $mid = (int) $me['id'];
    if ($rule === 'participants') return (bool) val('SELECT 1 FROM raffle_entries WHERE raffle_id = ? AND member_id = ?', [$r['id'], $mid])
        || (bool) val("SELECT 1 FROM raffle_requests WHERE raffle_id = ? AND member_id = ? AND status = 'approved'", [$r['id'], $mid]);
    foreach (json_decode((string) ($r['results'] ?? ''), true) ?: [] as $w) if ((int) $w['member_id'] === $mid) return true;
    return false;
}

/* ---------- prize claim: winners must message one of the owner's Telegram IDs within N hours ---------- */
function prize_claim_on(): bool { return setting_on('prize_claim_on'); }
function prize_claim_hours(): int { return max(1, min(720, (int) setting('prize_claim_hours', '24'))); }

/** the owner's Telegram IDs as [@name => https://t.me/name] */
function prize_claim_ids(): array
{
    $out = [];
    foreach (preg_split('~[\r\n,،]+~u', (string) setting('prize_claim_ids', '')) as $id) {
        $id = trim(preg_replace('~^(https?://)?(t\.me|telegram\.me)/~i', '', $id), " @/\t");
        if ($id !== '' && preg_match('~^[A-Za-z0-9_]{3,40}$~', $id)) $out['@' . $id] = 'https://t.me/' . $id;
    }
    return $out;
}

/** deadline (unix time) for a prize won at $wonAt */
function prize_claim_deadline(string $wonAt): int { return strtotime($wonAt) + prize_claim_hours() * 3600; }

/** one-line message for notifications (no IDs in the text — the Telegram buttons are on the prizes page) */
function prize_claim_text(?string $wonAt = null): string
{
    if (!prize_claim_on()) return 'به زودی برای تحویل جایزه باهات تماس می‌گیریم.';
    $until = $wonAt ? ' (تا ' . jdate('l j F — ساعت H:i', prize_claim_deadline($wonAt)) . ')' : '';
    return '📩 برای دریافت جایزه تا ' . fa_num((string) prize_claim_hours()) . ' ساعت' . $until . ' از دکمه‌ی تلگرام توی «جایزه‌های من» بهمون پیام بده؛ بعد از این زمان جایزه باطل می‌شه.';
}

/** the Telegram logo */
function tg_logo(int $s = 22): string
{
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#29a9eb"/><path fill="#fff" d="M5.4 11.7 17 7.2c.5-.2 1 .1.8.9l-2 9.3c-.1.6-.5.8-1 .5l-3-2.2-1.5 1.4c-.2.2-.3.3-.6.3l.2-3.1 5.6-5c.2-.2 0-.3-.4-.1l-6.9 4.3-3-.9c-.6-.2-.6-.6.2-.9Z"/></svg>';
}

/** Telegram buttons (logo only) for the owner's IDs */
function prize_claim_buttons(): string
{
    $h = ''; $n = 0;
    foreach (prize_claim_ids() as $id => $link) $h .= '<a class="pc-id" href="' . e($link) . '" target="_blank" rel="noopener" title="پیام در تلگرام" aria-label="پیام در تلگرام">' . tg_logo(24) . (count(prize_claim_ids()) > 1 ? '<small>' . fa_num((string) ++$n) . '</small>' : '') . '</a>';
    return $h !== '' ? $h : '<a class="pc-id pc-sup" href="' . e(url('support')) . '" title="پشتیبانی">💬</a>';
}

/** a box for the winner: deadline, time left and the Telegram button(s) */
function prize_claim_box(string $wonAt, string $prize = ''): string
{
    if (!prize_claim_on()) return '';
    $end = prize_claim_deadline($wonAt); $left = $end - time();
    return '<div class="pc-box' . ($left <= 0 ? ' is-over' : '') . '">'
        . '<div class="pc-head"><span class="pc-ic">' . ($left > 0 ? '📩' : '⌛') . '</span><div><b>' . ($left > 0 ? 'جایزه‌ت رو بگیر' . ($prize !== '' ? ': ' . e($prize) : '') : 'مهلت دریافت جایزه تموم شد') . '</b>'
        . '<small>' . ($left > 0 ? e(raffle_left_text($left)) . ' وقت داری (تا ' . e(jdate('l j F — H:i', $end)) . ') از دکمه‌ی تلگرام بهمون پیام بدی؛ بعدش جایزه باطل می‌شه.' : 'مهلت ' . fa_num((string) prize_claim_hours()) . ' ساعته‌ی پیام دادن گذشته و جایزه باطل شده.') . '</small></div>'
        . ($left > 0 ? '<div class="pc-ids">' . prize_claim_buttons() . '</div>' : '') . '</div></div>';
}

/** full name of a member by id (for results saved before full names were stored) */
function raffle_member_full(int $mid, string $fallback = ''): string
{
    static $cache = [];
    if (!isset($cache[$mid])) { $m = $mid ? row('SELECT first_name, last_name FROM members WHERE id = ?', [$mid]) : null; $cache[$mid] = $m ? raffle_full_name($m) : $fallback; }
    return $cache[$mid] ?: $fallback;
}

function raffle_full_name(array $m): string
{
    return trim(trim((string) ($m['first_name'] ?? '')) . ' ' . trim((string) ($m['last_name'] ?? ''))) ?: 'کاربر';
}

/** approved tickets per member (paid mode) */
function raffle_ticket_counts(int $rid): array
{
    $out = [];
    foreach (rows("SELECT member_id, SUM(qty) n FROM raffle_requests WHERE raffle_id = ? AND status = 'approved' GROUP BY member_id", [$rid]) as $x) $out[(int) $x['member_id']] = (int) $x['n'];
    return $out;
}

/** every participant with weight and approximate chance */
function raffle_pool(array $r): array
{
    $rid = (int) $r['id'];
    $entries = rows('SELECT e.*, m.first_name, m.last_name, m.member_code FROM raffle_entries e JOIN members m ON m.id = e.member_id WHERE e.raffle_id = ? ORDER BY e.id', [$rid]);
    $tickets = $r['mode'] === 'paid' ? raffle_ticket_counts($rid) : [];
    $pool = [];
    foreach ($entries as $e) {
        $mid = (int) $e['member_id'];
        if ($r['mode'] === 'paid') { $w = $tickets[$mid] ?? 0; if ($w <= 0) continue; }
        elseif ($r['mode'] === 'referral') $w = 100 + (int) round((int) $e['bonus'] * (float) $r['ticket_bonus']);
        else $w = 100;
        $pool[$mid] = ['member_id' => $mid, 'name' => raffle_public_name($e), 'full' => raffle_full_name($e), 'code' => $e['member_code'], 'bonus' => (int) $e['bonus'],
            'tickets' => $tickets[$mid] ?? 0, 'w' => $w, 'joined' => $e['created_at']];
    }
    $total = array_sum(array_column($pool, 'w'));
    $k = count(raffle_prizes($r));
    foreach ($pool as &$p) $p['chance'] = $total > 0 ? min(100, round($p['w'] / $total * min($k, max(1, count($pool))) * 100, 2)) : 0;
    unset($p);
    return $pool;
}

function raffle_stats(array $r): array
{
    $rid = (int) $r['id'];
    $pool = raffle_pool($r);
    $sold = $r['mode'] === 'paid' ? (int) val("SELECT COALESCE(SUM(qty), 0) FROM raffle_requests WHERE raffle_id = ? AND status = 'approved'", [$rid]) : 0;
    $pending = $r['mode'] === 'paid' ? (int) val("SELECT COUNT(*) FROM raffle_requests WHERE raffle_id = ? AND status = 'pending'", [$rid]) : 0;
    $need = (int) $r['min_participants'];
    $needT = $r['mode'] === 'paid' ? (int) $r['min_tickets'] : 0;
    $ready = count($pool) >= max(1, $need) && $sold >= $needT && count($pool) >= 1;
    return ['participants' => count($pool), 'sold' => $sold, 'pending' => $pending, 'need' => $need, 'need_tickets' => $needT, 'ready' => $ready,
        'left' => max(0, strtotime($r['draw_at']) - time()), 'pool' => $pool];
}

/** what the visitor sees about themselves */
function raffle_mine(array $r, ?array $m): ?array
{
    if (!$m) return null;
    $rid = (int) $r['id']; $mid = (int) $m['id'];
    $e = row('SELECT * FROM raffle_entries WHERE raffle_id = ? AND member_id = ?', [$rid, $mid]);
    $pool = raffle_pool($r);
    $req = rows('SELECT * FROM raffle_requests WHERE raffle_id = ? AND member_id = ? ORDER BY id DESC', [$rid, $mid]);
    $approved = array_values(array_filter($req, fn($x) => $x['status'] === 'approved'));
    return [
        'joined' => (bool) $e || $approved, 'bonus' => (int) ($e['bonus'] ?? 0), 'chance' => $pool[$mid]['chance'] ?? 0,
        'tickets' => array_sum(array_map(fn($x) => (int) $x['qty'], $approved)),
        'ranges' => array_map(fn($x) => [(int) $x['from_no'], (int) $x['to_no']], $approved),
        'pending' => array_sum(array_map(fn($x) => $x['status'] === 'pending' ? (int) $x['qty'] : 0, $req)),
        'requests' => $req,
        'invite' => invite_link($m, 'r' . (int) $r['id']),
        'invited' => (int) val('SELECT COUNT(*) FROM raffle_entries WHERE raffle_id = ? AND ref_by = ?', [$rid, $mid]),
    ];
}

function raffle_open(array $r): bool
{
    return $r['status'] === 'active' && strtotime($r['draw_at']) > time();
}

function raffle_join(array $r, array $m, string $refCode = ''): array
{
    if (!raffle_open($r)) return ['ok' => false, 'error' => 'ثبت‌نام این قرعه‌کشی بسته است.'];
    if ($r['mode'] === 'paid') return ['ok' => false, 'error' => 'برای این قرعه‌کشی باید تیکت بخری.'];
    $rid = (int) $r['id']; $mid = (int) $m['id'];
    if (val('SELECT id FROM raffle_entries WHERE raffle_id = ? AND member_id = ?', [$rid, $mid])) return ['ok' => true, 'already' => true];
    $refId = null;
    if ($r['mode'] === 'referral' && $refCode === '' && !empty($m['invited_by']) && strtotime((string) $m['created_at']) >= strtotime((string) $r['created_at'])) $refCode = (string) val('SELECT COALESCE(invite_code, member_code) FROM members WHERE id = ?', [(int) $m['invited_by']]);
    if ($r['mode'] === 'referral' && $refCode !== '') {
        $ref = invite_owner(mb_substr($refCode, 0, 20));
        if ($ref && (int) $ref['id'] !== $mid && val('SELECT id FROM raffle_entries WHERE raffle_id = ? AND member_id = ?', [$rid, $ref['id']])) $refId = (int) $ref['id'];
    }
    try {
        insert('raffle_entries', ['raffle_id' => $rid, 'member_id' => $mid, 'ref_by' => $refId, 'bonus' => 0, 'created_at' => now()]);
    } catch (Throwable $e) {
        return ['ok' => true, 'already' => true];   // double click
    }
    if ($refId) {
        $cap = (int) $r['max_referrals'];
        q('UPDATE raffle_entries SET bonus = bonus + 1 WHERE raffle_id = ? AND member_id = ?' . ($cap > 0 ? ' AND bonus < ' . $cap : ''), [$rid, $refId]);
        notify('m:' . $refId, 'raffle', $rid, '🤝', 'یک دوستت با لینک تو شرکت کرد', 'شانست توی «' . $r['title'] . '» بیشتر شد.', 'raffle/' . $r['slug'], 'success');
    }
    return ['ok' => true];
}

function raffle_request_tickets(array $r, array $m, int $qty, array $file): array
{
    if (!raffle_open($r) || $r['mode'] !== 'paid') return ['ok' => false, 'error' => 'خرید تیکت برای این قرعه‌کشی ممکن نیست.'];
    $rid = (int) $r['id']; $mid = (int) $m['id'];
    $have = (int) val("SELECT COALESCE(SUM(qty), 0) FROM raffle_requests WHERE raffle_id = ? AND member_id = ? AND status IN ('pending','approved')", [$rid, $mid]);
    $cap = (int) $r['max_tickets_user'];
    $room = $cap > 0 ? $cap - $have : 1000;
    if ($qty < 1) return ['ok' => false, 'error' => 'تعداد تیکت را مشخص کن.'];
    if ($room <= 0) return ['ok' => false, 'error' => 'به سقف خرید تیکت (' . fa_num((string) $cap) . ' عدد) رسیدی.'];
    if ($qty > $room) return ['ok' => false, 'error' => 'حداکثر ' . fa_num((string) $room) . ' تیکت دیگه می‌تونی بخری.'];
    if (val("SELECT COUNT(*) FROM raffle_requests WHERE raffle_id = ? AND member_id = ? AND status = 'pending'", [$rid, $mid]) >= 3) return ['ok' => false, 'error' => 'چند درخواست در انتظار بررسی داری؛ کمی صبر کن.'];
    $up = upload_private_image($file, 'raffle');
    if (!$up['ok']) return $up;
    $amount = $qty * (int) $r['ticket_price'];
    $id = insert('raffle_requests', ['raffle_id' => $rid, 'member_id' => $mid, 'qty' => $qty, 'amount' => $amount, 'receipt' => $up['path'], 'status' => 'pending', 'created_at' => now()]);
    notify('admin', 'raffle_req', (int) $id, '🎟️', 'خرید تیکت — ' . $r['title'], $m['first_name'] . ' ' . $m['last_name'] . ' · ' . fa_num((string) $qty) . ' تیکت · ' . money($amount) . ' تومان', 'admin/raffle-view.php?id=' . $rid, 'warn');
    return ['ok' => true, 'id' => $id];
}

function raffle_review_request(int $reqId, bool $approve, string $note = ''): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $req = row('SELECT * FROM raffle_requests WHERE id = ? FOR UPDATE', [$reqId]);
        if (!$req || $req['status'] !== 'pending') { $pdo->rollBack(); return ['ok' => false, 'error' => 'این درخواست قبلاً بررسی شده.']; }
        $r = row('SELECT * FROM raffles WHERE id = ? FOR UPDATE', [$req['raffle_id']]);
        if ($approve) {
            if (!$r || !in_array($r['status'], ['active', 'draft'], true)) { $pdo->rollBack(); return ['ok' => false, 'error' => 'قرعه‌کشی دیگه باز نیست.']; }
            $from = (int) val("SELECT COALESCE(MAX(to_no), 0) FROM raffle_requests WHERE raffle_id = ? AND status = 'approved'", [$r['id']]) + 1;
            $to = $from + (int) $req['qty'] - 1;
            q("UPDATE raffle_requests SET status = 'approved', from_no = ?, to_no = ?, note = ?, reviewed_at = ? WHERE id = ?", [$from, $to, $note ?: null, now(), $reqId]);
            q('INSERT IGNORE INTO raffle_entries (raffle_id, member_id, bonus, created_at) VALUES (?, ?, 0, ?)', [$r['id'], $req['member_id'], now()]);
            $pdo->commit();
            notify('m:' . $req['member_id'], 'raffle', (int) $r['id'], '🎟️', 'تیکت‌هات تأیید شد', 'شماره‌ها: ' . fa_num($from . ($to > $from ? ' تا ' . $to : '')) . ' — «' . $r['title'] . '»', 'raffle/' . $r['slug'], 'success');
            unnotify('admin', 'raffle_req', $reqId);
            return ['ok' => true, 'from' => $from, 'to' => $to];
        }
        q("UPDATE raffle_requests SET status = 'rejected', note = ?, reviewed_at = ? WHERE id = ?", [$note ?: null, now(), $reqId]);
        $pdo->commit();
        notify('m:' . $req['member_id'], 'raffle', (int) $req['raffle_id'], '⚠️', 'خرید تیکت تأیید نشد', $note ?: 'فیش پرداخت تأیید نشد؛ با پشتیبانی تماس بگیر.', 'raffle/' . ($r['slug'] ?? ''), 'warn');
        unnotify('admin', 'raffle_req', $reqId);
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** weighted pick without replacement (integer weights, CSPRNG) */
function raffle_pick(array &$pool): ?array
{
    $total = array_sum(array_column($pool, 'w'));
    if ($total <= 0) return null;
    $x = random_int(1, $total);
    foreach ($pool as $k => $p) { $x -= $p['w']; if ($x <= 0) { unset($pool[$k]); return $p; } }
    return null;
}

/** runs the draw once its time has come (or now, by the admin). Safe against two visitors arriving together. */
function raffle_maybe_draw(array $r, bool $force = false): array
{
    if ($r['status'] !== 'active' || (!$force && strtotime($r['draw_at']) > time())) return $r;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = row('SELECT * FROM raffles WHERE id = ? FOR UPDATE', [$r['id']]);
        if (!$r || $r['status'] !== 'active') { $pdo->commit(); return $r ?: []; }
        $st = raffle_stats($r);
        if (!$st['ready']) {
            q("UPDATE raffles SET status = 'failed', drawn_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $r['id']]);
            $pdo->commit();
            foreach (array_keys($st['pool']) as $mid) notify('m:' . $mid, 'raffle', (int) $r['id'], 'ℹ️', 'قرعه‌کشی برگزار نشد', '«' . $r['title'] . '» به حد نصاب نرسید.', 'raffle/' . $r['slug'], 'info');
            notify('admin', 'raffle', (int) $r['id'], 'ℹ️', 'قرعه‌کشی به حد نصاب نرسید', $r['title'], 'admin/raffle-view.php?id=' . $r['id'], 'warn');
            return row('SELECT * FROM raffles WHERE id = ?', [$r['id']]);
        }
        $prizes = raffle_prizes($r);
        $results = [];
        if ($r['mode'] === 'paid') {
            $ranges = rows("SELECT member_id, from_no, to_no FROM raffle_requests WHERE raffle_id = ? AND status = 'approved' ORDER BY from_no", [$r['id']]);
            $max = (int) max(array_column($ranges, 'to_no') ?: [0]);
            $won = [];
            foreach ($prizes as $i => $pz) {
                for ($try = 0; $try < 5000; $try++) {
                    $n = random_int(1, $max); $owner = null;
                    foreach ($ranges as $g) if ($n >= $g['from_no'] && $n <= $g['to_no']) { $owner = (int) $g['member_id']; break; }
                    if ($owner && !isset($won[$owner])) break;
                    $owner = null;
                }
                if (!$owner) break;
                $won[$owner] = true;
                $p = $st['pool'][$owner];
                $results[] = ['prize' => $pz['title'], 'member_id' => $owner, 'name' => $p['name'], 'full' => $p['full'], 'ticket' => $n, 'chance' => $p['chance']];
            }
        } else {
            $pool = $st['pool'];
            foreach ($prizes as $pz) {
                $p = raffle_pick($pool);
                if (!$p) break;
                $results[] = ['prize' => $pz['title'], 'member_id' => $p['member_id'], 'name' => $p['name'], 'full' => $p['full'], 'chance' => $p['chance']];
            }
        }
        q("UPDATE raffles SET status = 'drawn', results = ?, draw_seed = ?, drawn_at = ?, updated_at = ? WHERE id = ?",
            [json_encode($results, JSON_UNESCAPED_UNICODE), bin2hex(random_bytes(12)), now(), now(), $r['id']]);
        $pdo->commit();
        foreach ($results as $i => $w) prize_create((int) $w['member_id'], 'raffle', (int) $r['id'], (string) $w['prize'], (string) ($prizes[$i]['emoji'] ?? ''), discount_norm($prizes[$i]['discount'] ?? null), null, (string) $r['title']);
        notify('admin', 'raffle', (int) $r['id'], '🏆', 'قرعه‌کشی برگزار شد', $r['title'] . ' — ' . implode('، ', array_column($results, 'full')), 'admin/raffle-view.php?id=' . $r['id'], 'success');
        return row('SELECT * FROM raffles WHERE id = ?', [$r['id']]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** called from busy pages: draws every raffle whose time has come (one cheap query when nothing is due) */
function raffles_due(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        foreach (rows("SELECT * FROM raffles WHERE status = 'active' AND draw_at <= ? LIMIT 5", [now()]) as $r) raffle_maybe_draw($r);
    } catch (Throwable $e) { nx_log_error($e); }
}

/** segments for the wheel: names (free / referral, sized by chance) or ticket numbers (paid) */
function raffle_wheel(array $r, array $pool, bool $admin = false): array
{
    $cap = 300;
    $results = json_decode((string) ($r['results'] ?? ''), true) ?: [];
    $winners = [];
    foreach ($results as $i => $w) $winners[(int) $w['member_id']] = $i;
    $nameOf = fn($mid) => isset($pool[$mid]) ? ($admin ? $pool[$mid]['full'] : $pool[$mid]['name']) : '';
    $seg = [];
    if ($r['mode'] === 'paid') {
        $ranges = rows("SELECT member_id, from_no, to_no FROM raffle_requests WHERE raffle_id = ? AND status = 'approved' ORDER BY from_no", [$r['id']]);
        $nums = [];
        foreach ($ranges as $g) for ($n = (int) $g['from_no']; $n <= (int) $g['to_no'] && count($nums) < 20000; $n++) $nums[$n] = (int) $g['member_id'];
        $winNums = [];
        foreach ($results as $i => $w) if (isset($w['ticket'])) $winNums[(int) $w['ticket']] = $i;
        $keys = array_keys($nums);
        if (count($keys) > $cap) {
            $rest = array_values(array_diff($keys, array_keys($winNums)));
            shuffle($rest);
            $keys = array_merge(array_keys($winNums), array_slice($rest, 0, $cap - count($winNums)));
            sort($keys);
        }
        foreach ($keys as $n) $seg[] = ['label' => fa_num((string) $n) . ' · ' . $nameOf($nums[$n]), 'w' => 1, 'win' => $winNums[$n] ?? null];
    } else {
        $list = array_values($pool);
        if (count($list) > $cap) {
            $keep = array_filter($list, fn($p) => isset($winners[$p['member_id']]));
            $rest = array_values(array_filter($list, fn($p) => !isset($winners[$p['member_id']])));
            shuffle($rest);
            $list = array_merge($keep, array_slice($rest, 0, $cap - count($keep)));
        }
        shuffle($list);   // neighbours on the wheel are random, not in join order
        foreach ($list as $p) $seg[] = ['label' => $admin ? $p['full'] : $p['name'], 'w' => max(1, $p['w']), 'win' => $winners[$p['member_id']] ?? null];
    }
    return $seg;
}

/** the promo box (uses the ad themes) */
function raffle_box_ad(array $r, array $st): array
{
    $prizes = raffle_prizes($r);
    $prize = $prizes[0]['title'] . (count($prizes) > 1 ? ' و ' . fa_num((string) (count($prizes) - 1)) . ' جایزه‌ی دیگه' : '');
    $prog = $r['mode'] === 'paid'
        ? fa_num((string) $st['sold']) . ($st['need_tickets'] ? ' از ' . fa_num((string) $st['need_tickets']) : '') . ' تیکت فروخته شده'
        : fa_num((string) $st['participants']) . ($st['need'] ? ' از ' . fa_num((string) $st['need']) : '') . ' نفر شرکت کردن';
    return [
        'id' => 0, 'no_label' => true, 'format' => 'card', 'theme' => (string) ($r['box_theme'] ?: 'neon'),
        'accent' => (string) ($r['box_accent'] ?: setting('color_primary', '#ff1f3d')), 'image' => (string) $r['image'],
        'link' => raffle_url($r), 'new_tab' => 0, 'heading' => $r['title'], 'emoji' => '🎰', 'badge' => 'قرعه‌کشی',
        'body' => '🎁 ' . $prize . "\n" . '👥 ' . $prog, 'button_text' => $r['mode'] === 'paid' ? 'خرید تیکت' : 'شرکت در قرعه‌کشی',
        'countdown' => 1, 'end_at' => $r['draw_at'],
    ];
}

/** a finished raffle keeps its result on the site for `result_hours` hours */
function raffle_result_live(array $r): bool
{
    return $r['status'] === 'drawn' && !empty($r['drawn_at']) && (int) ($r['result_hours'] ?? 24) > 0
        && strtotime((string) $r['drawn_at']) + (int) ($r['result_hours'] ?? 24) * 3600 > time();
}

function raffle_result_ad(array $r): array
{
    $res = json_decode((string) ($r['results'] ?? ''), true) ?: [];
    $who = $res ? $res[0]['name'] . (count($res) > 1 ? ' و ' . fa_num((string) (count($res) - 1)) . ' نفر دیگه' : '') : '';
    return [
        'id' => 0, 'no_label' => true, 'format' => 'card', 'theme' => (string) ($r['box_theme'] ?: 'neon'),
        'accent' => (string) ($r['box_accent'] ?: setting('color_primary', '#ff1f3d')), 'image' => (string) $r['image'],
        'link' => raffle_url($r) . '?show=1', 'new_tab' => 0, 'heading' => $r['title'], 'emoji' => '🏆', 'badge' => 'برگزار شد',
        'body' => '🎉 قرعه‌کشی انجام شد!' . ($who ? "\n" . '🏆 برنده: ' . $who : ''), 'button_text' => 'مشاهده‌ی برنده و گردونه',
    ];
}

/**
 * The raffle promo box — its own compact design (art tile, live tag, prize, progress, countdown, CTA),
 * wrapped in the chosen ad theme so the theme's border / glow / moving layers still apply.
 * Narrow places (sidebar, phones) re-flow through a container query.
 */
function raffle_box_html(array $r, ?array $st = null, bool $preview = false): string
{
    $theme = array_key_exists((string) ($r['box_theme'] ?? ''), ad_themes()) ? (string) $r['box_theme'] : 'neon';
    $accent = valid_hex((string) ($r['box_accent'] ?? ''), '') ?: valid_hex((string) setting('color_primary', '#ff1f3d'), '#ff1f3d');
    $drawn = ($r['status'] ?? '') === 'drawn';
    $prizes = raffle_prizes($r);
    $prize = ($prizes[0]['title'] ?? '') ?: 'جایزه‌ی ویژه';
    $more = count($prizes) > 1 ? ' + ' . fa_num((string) (count($prizes) - 1)) . ' جایزه' : '';
    $href = $preview ? '#' : raffle_url($r) . ($drawn ? '?show=1' : '');
    $img = !empty($r['image']) ? media_url((string) $r['image']) : '';
    $mode = raffle_modes()[$r['mode']] ?? raffle_modes()['free'];

    $art = '<span class="rfx-art" aria-hidden="true"><span class="rfx-ring"></span>'
        . ($img ? '<img src="' . e($img) . '" alt="" loading="lazy">' : '<span class="rfx-emoji">' . e($drawn ? '🏆' : ($prizes[0]['emoji'] ?? '🎁')) . '</span>')
        . '</span>';
    if ($drawn) {
        $res = json_decode((string) ($r['results'] ?? ''), true) ?: [];
        $who = $res ? preg_replace('~\s+\S\.$~u', '', (string) $res[0]['name']) . (count($res) > 1 ? ' و ' . fa_num((string) (count($res) - 1)) . ' نفر دیگه' : '') : '';
        $tag = '<span class="rfx-tag rfx-done">🏆 برگزار شد</span>';
        $mid = '<span class="rfx-win"><small>برنده</small><b>' . e($who ?: '—') . '</b></span>';
        $side = '<span class="rfx-cta">🎡 دیدن گردونه ' . icon('arrow-left', 15) . '</span>';
    } else {
        $st = $st ?: ['participants' => 0, 'need' => (int) ($r['min_participants'] ?? 0), 'sold' => 0, 'need_tickets' => (int) ($r['min_tickets'] ?? 0)];
        $paid = $r['mode'] === 'paid';
        $have = $paid ? (int) $st['sold'] : (int) $st['participants'];
        $need = $paid ? (int) $st['need_tickets'] : (int) $st['need'];
        $pct = $need ? min(100, round($have / max(1, $need) * 100)) : 100;
        $left = max(0, strtotime((string) $r['draw_at']) - time());
        $tag = '<span class="rfx-tag"><i class="rfx-dot"></i> قرعه‌کشی · ' . e($mode[0]) . '</span>';
        $mid = '<span class="rfx-prog"><span class="rfx-bar"><i style="width:' . $pct . '%"></i></span><small>' . ($paid ? '🎟️ ' : '👥 ') . fa_num((string) $have)
            . ($need ? ' از ' . fa_num((string) $need) : '') . ($paid ? ' تیکت' : ' نفر') . '</small></span>';
        $side = '<span class="nxp-countdown rfx-cd" data-end="' . e(date('c', strtotime((string) $r['draw_at']))) . '">'
            . '<b data-u="d">' . fa_num((string) intdiv($left, 86400)) . '</b><small>روز</small><b data-u="h">' . fa_num((string) intdiv($left % 86400, 3600)) . '</b><small>ساعت</small>'
            . '<b data-u="m">' . fa_num((string) intdiv($left % 3600, 60)) . '</b><small>دقیقه</small><b data-u="s">' . fa_num((string) ($left % 60)) . '</b><small>ثانیه</small></span>'
            . '<span class="rfx-cta">' . ($paid ? '🎟️ خرید تیکت' : '🎰 شرکت کن') . ' ' . icon('arrow-left', 15) . '</span>';
    }
    $title = e(($r['title'] ?? '') ?: 'قرعه‌کشی بزرگ');
    return '<div class="nxp nxp-theme-' . $theme . ' rfx' . ($drawn ? ' rfx-is-done' : '') . '" style="--nxp:' . $accent . ';--nxp-rgb:' . hex_to_rgb($accent) . '">'
        . ad_deco($theme) . ($theme === 'ribbon' ? '<span class="nxp-ribbon">ویژه</span>' : '')
        . '<a class="nxp-inner rfx-in" href="' . e($href) . '">'
        . $art
        . '<span class="rfx-body">' . $tag
        . '<strong class="nxp-title rfx-title"' . ($theme === 'glitch' ? ' data-text="' . $title . '"' : '') . '>' . $title . '</strong>'
        . '<span class="rfx-prize">🎁 ' . e($prize) . '<em>' . e($more) . '</em></span>' . $mid . '</span>'
        . '<span class="rfx-side">' . $side . '</span>'
        . '</a></div>';
}

function raffle_slot(string $position): string
{
    if (!setting_on('footer_raffle_link')) return '';
    static $list = null;
    if ($list === null) {
        $list = [];
        try {
            raffles_due();
            $list = array_values(array_filter(rows("SELECT * FROM raffles WHERE box_on = 1 AND ((status = 'active' AND draw_at > ?) OR (status = 'drawn' AND drawn_at IS NOT NULL)) ORDER BY draw_at", [now()]),
                fn($r) => $r['status'] === 'active' || raffle_result_live($r)));
        } catch (Throwable $e) {}
    }
    $out = '';
    foreach ($list as $r) {
        $places = array_filter(array_map('trim', explode(',', (string) $r['box_places'])));
        if (!in_array($position, $places, true)) continue;
        if ($position === 'header_top') { $pz = raffle_prizes($r); $out .= top_strip('r' . $r['id'], '🎰', $r['title'] . ' — ' . ($pz[0]['emoji'] ?? '🎁') . ' ' . $pz[0]['title'] . (count($pz) > 1 ? ' + ' . fa_num((string) (count($pz) - 1)) . ' جایزه' : ''), raffle_url($r), $r['status'] === 'drawn' ? 'نتیجه' : 'شرکت', (string) ($r['box_accent'] ?? '')); continue; }
        $out .= '<div class="nxp-slot nxp-pos-' . e($position) . ' raffle-promo" data-reveal>' . raffle_box_html($r, $r['status'] === 'drawn' ? null : raffle_stats($r)) . '</div>';
    }
    return $out;
}


/** everything the raffle page needs (also returned by the API while the page is open) */
function raffle_state(array $r, ?array $me, bool $admin = false): array
{
    $st = raffle_stats($r);
    $mine = raffle_mine($r, $me);
    $pool = $st['pool'];
    uasort($pool, fn($a, $b) => $b['w'] <=> $a['w']);
    return [
        'id' => (int) $r['id'], 'status' => $r['status'], 'mode' => $r['mode'], 'left' => $st['left'], 'draw_at' => date('c', strtotime($r['draw_at'])),
        'participants' => $st['participants'], 'need' => $st['need'], 'sold' => $st['sold'], 'need_tickets' => $st['need_tickets'],
        'results' => array_map(fn($w) => ['prize' => $w['prize'], 'name' => $admin ? ($st['pool'][(int) $w['member_id']]['full'] ?? $w['full'] ?? raffle_member_full((int) $w['member_id'], (string) $w['name'])) : ($st['pool'][(int) $w['member_id']]['name'] ?? preg_replace('~\s+\S\.$~u', '', (string) $w['name'])), 'ticket' => $w['ticket'] ?? null, 'chance' => $w['chance'] ?? 0, 'me' => $me && (int) $w['member_id'] === (int) $me['id']],
            json_decode((string) ($r['results'] ?? ''), true) ?: []),
        'wheel' => raffle_wheel($r, $st['pool'], $admin),
        'admin' => $admin,
        'people' => array_slice(array_map(fn($p) => ['name' => $admin ? $p['full'] : $p['name'], 'chance' => $p['chance'], 'tickets' => $p['tickets'], 'bonus' => $p['bonus'], 'me' => $me && $p['member_id'] === (int) $me['id']], array_values($pool)), 0, 300),
        'me' => $mine ? ['joined' => $mine['joined'], 'chance' => $mine['chance'], 'tickets' => $mine['tickets'], 'ranges' => $mine['ranges'], 'pending' => $mine['pending'], 'bonus' => $mine['bonus'], 'invited' => $mine['invited']] : null,
    ];
}

/** "۲ روز و ۳ ساعت" */
function raffle_left_text(int $sec): string
{
    $d = intdiv($sec, 86400); $h = intdiv($sec % 86400, 3600); $m = intdiv($sec % 3600, 60);
    $parts = [];
    if ($d) $parts[] = fa_num((string) $d) . ' روز';
    if ($h) $parts[] = fa_num((string) $h) . ' ساعت';
    if (!$d && $m) $parts[] = fa_num((string) $m) . ' دقیقه';
    return $parts ? implode(' و ', array_slice($parts, 0, 2)) : 'کمتر از یک دقیقه';
}
