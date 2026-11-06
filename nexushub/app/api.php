<?php
/**
 * API های عمومی سایت (واکنش، نظر)
 * @var array $seg
 */
if (!is_post()) json_out(['ok' => false, 'error' => 'method'], 405);
if (!csrf_valid()) json_out(['ok' => false, 'error' => 'نشست منقضی شده؛ صفحه را رفرش کنید.'], 403);

switch ($seg[1] ?? '') {

    case 'react':
        if (!setting_on('reactions_enabled')) json_out(['ok' => false, 'error' => 'غیرفعال'], 403);
        $postId = (int) ($_POST['post_id'] ?? 0);
        $emoji = (string) ($_POST['emoji'] ?? '');
        $allowed = array_filter(array_map('trim', explode(',', (string) setting('reactions_list'))));
        if (!in_array($emoji, $allowed, true)) json_out(['ok' => false, 'error' => 'واکنش نامعتبر'], 422);
        if (!val('SELECT id FROM posts WHERE id = ? AND ' . pub_where('posts'), [$postId])) json_out(['ok' => false, 'error' => 'خبر یافت نشد'], 404);

        $h = ip_hash();
        $prev = row('SELECT emoji FROM reaction_log WHERE post_id = ? AND ip_hash = ?', [$postId, $h]);
        $mine = $emoji;
        db()->beginTransaction();
        if ($prev) {
            q('UPDATE reactions SET cnt = GREATEST(cnt, 1) - 1 WHERE post_id = ? AND emoji = ?', [$postId, $prev['emoji']]);
            if ($prev['emoji'] === $emoji) {
                q('DELETE FROM reaction_log WHERE post_id = ? AND ip_hash = ?', [$postId, $h]);
                $mine = null;
            } else {
                q('UPDATE reaction_log SET emoji = ?, created_at = ? WHERE post_id = ? AND ip_hash = ?', [$emoji, now(), $postId, $h]);
            }
        } else {
            q('INSERT INTO reaction_log (post_id, ip_hash, emoji, created_at) VALUES (?, ?, ?, ?)', [$postId, $h, $emoji, now()]);
        }
        if ($mine !== null) {
            q('INSERT INTO reactions (post_id, emoji, cnt) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE cnt = cnt + 1', [$postId, $emoji]);
        }
        db()->commit();
        json_out(['ok' => true, 'counts' => post_reactions($postId), 'mine' => $mine]);

    case 'comment':
        if (!setting_on('comments_enabled')) json_out(['ok' => false, 'error' => 'ارسال نظر غیرفعال است.'], 403);
        $postId = (int) ($_POST['post_id'] ?? 0);
        $post = row('SELECT id, allow_comments FROM posts WHERE id = ? AND ' . pub_where('posts'), [$postId]);
        if (!$post || !$post['allow_comments']) json_out(['ok' => false, 'error' => 'امکان ثبت نظر برای این خبر وجود ندارد.'], 403);
        if (!empty($_POST['website'])) json_out(['ok' => true, 'message' => 'نظر شما ثبت شد.']); // honeypot

        $user = current_user();
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 60);
        $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 2000);
        if ($user) $name = $user['display_name'] ?: $user['username'];
        elseif ($member = current_member()) { $name = member_full_name($member); $email = $member['email']; }
        if (mb_strlen($name) < 2) json_out(['ok' => false, 'error' => 'نام خود را وارد کنید.'], 422);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'ایمیل معتبر نیست.'], 422);
        if (mb_strlen($body) < 3) json_out(['ok' => false, 'error' => 'متن نظر خیلی کوتاه است.'], 422);
        if (!$user && time() - (int) ($_SESSION['last_comment'] ?? 0) < 30) json_out(['ok' => false, 'error' => 'لطفاً ۳۰ ثانیه صبر کنید.'], 429);
        if (!$user && preg_match_all('~https?://~i', $body) > 2) json_out(['ok' => false, 'error' => 'لینک‌های زیادی در نظر وجود دارد.'], 422);

        $parent = (int) ($_POST['parent_id'] ?? 0);
        if ($parent && !val('SELECT id FROM comments WHERE id = ? AND post_id = ? AND parent_id IS NULL', [$parent, $postId])) $parent = 0;

        $status = ($user || !setting_on('comments_moderation')) ? 'approved' : 'pending';
        insert('comments', [
            'post_id' => $postId, 'parent_id' => $parent ?: null, 'user_id' => $user['id'] ?? null,
            'name' => $name, 'email' => $email ?: null, 'body' => $body, 'status' => $status,
            'ip' => client_ip(), 'created_at' => now(),
        ]);
        $_SESSION['last_comment'] = time();
        json_out([
            'ok' => true,
            'approved' => $status === 'approved',
            'message' => $status === 'approved' ? 'نظر شما منتشر شد 🎉' : 'نظر شما ثبت شد و پس از تایید نمایش داده می‌شود ✅',
        ]);

    case 'ping':   // heartbeat from open pages: keeps the visitor "online" and returns the live count
        track_online();
        [$tot, $mem] = online_count();
        $out = ['ok' => true, 'online' => max(1, $tot), 'online_fa' => fa_num((string) max(1, $tot))];
        if ($cm = current_member()) {
            $out['notif'] = notifs_unread('m:' . $cm['id']);
            $latest = row('SELECT * FROM notifications WHERE audience = ? AND is_read = 0 ORDER BY updated_at DESC LIMIT 1', ['m:' . $cm['id']]);
            if ($latest) $out['latest'] = notif_json($latest);
        }
        json_out($out);

    /* ---------- shop ---------- */
    case 'cart-add':
        if (!shop_on()) json_out(['ok' => false, 'error' => 'فروشگاه غیرفعال است.'], 403);
        $p = row('SELECT * FROM products WHERE id = ?', [(int) ($_POST['pid'] ?? 0)]);
        if (!$p) json_out(['ok' => false, 'error' => 'محصول یافت نشد.'], 404);
        [$vals, $ferr] = validate_product_fields($p, (array) ($_POST['f'] ?? []));
        if ($ferr) json_out(['ok' => false, 'errors' => $ferr, 'error' => reset($ferr)], 422);
        $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float) en_num((string) $_POST['amount']) : null;
        $e = cart_add($p, (int) ($_POST['vid'] ?? 0), $amount, (int) ($_POST['qty'] ?? 1), $vals);
        if ($e !== '') json_out(['ok' => false, 'error' => $e], 422);
        json_out(['ok' => true, 'count' => cart_count(), 'count_fa' => fa_num((string) cart_count())]);

    case 'market':  // live market board (shop page): refreshes the cache in the background of the visitor's page
        if (!setting_on('shop_tickers_on')) json_out(['ok' => false], 404);
        $b = market_board(true, !empty($_POST['force']));
        foreach ($b['items'] as &$it) { $it['fa'] = $it['toman'] ? money($it['toman']) : '—'; $it['usd_fa'] = market_usd($it['usd']); }
        unset($it);
        json_out(['ok' => true, 'items' => $b['items'], 'src' => $b['src'] ?? '', 'at' => $b['at'] ? jdate('H:i', $b['at']) : '']);

    case 'price':   // live price for a custom amount (product page)
        $p = row("SELECT * FROM products WHERE id = ? AND status = 'active'", [(int) ($_POST['pid'] ?? 0)]);
        if (!$p) json_out(['ok' => false], 404);
        $t = custom_price($p, (float) en_num((string) ($_POST['amount'] ?? '0')));
        json_out(['ok' => $t !== null, 'toman' => $t, 'fa' => money($t)]);

    case 'order-status':
        $cm = current_member();
        $o = row('SELECT code, status, member_id, updated_at FROM orders WHERE code = ?', [strtoupper((string) ($_POST['code'] ?? ''))]);
        if (!$o || ((!$cm || (int) $cm['id'] !== (int) $o['member_id']) && !current_user())) json_out(['ok' => false], 404);
        shop_expire_due();
        $o = row('SELECT code, status, updated_at FROM orders WHERE code = ?', [$o['code']]);
        json_out(['ok' => true, 'status' => $o['status'], 'v' => strtotime($o['updated_at'])]);

    case 'ticket-poll':
        $cm = current_member();
        $t = row('SELECT * FROM tickets WHERE code = ?', [strtoupper((string) ($_POST['code'] ?? ''))]);
        if (!$t || ((!$cm || (int) $cm['id'] !== (int) $t['member_id']) && !current_user())) json_out(['ok' => false], 404);
        $last = (int) val('SELECT MAX(id) FROM ticket_messages WHERE ticket_id = ?', [$t['id']]);
        json_out(['ok' => true, 'last' => $last, 'status' => $t['status']]);

    case 'notifs':
        $cm = current_member();
        if (!$cm) json_out(['ok' => false], 403);
        $aud = 'm:' . $cm['id'];
        if (!empty($_POST['read'])) q('UPDATE notifications SET is_read = 1 WHERE audience = ?', [$aud]);
        json_out(['ok' => true, 'items' => array_map('notif_json', notifs_for($aud)), 'unread' => notifs_unread($aud)]);

    case 'story-view':
        $sid = (int) ($_POST['story_id'] ?? 0);
        $seen = $_SESSION['story_seen'] ?? [];
        if ($sid && !current_user() && !in_array($sid, $seen, true)) {   // once per visitor session; panel users not counted
            q('UPDATE stories SET views = views + 1 WHERE id = ?', [$sid]);
            $seen[] = $sid;
            $_SESSION['story_seen'] = array_slice($seen, -100);
        }
        json_out(['ok' => true]);

    /* ---------------- ورود و ثبت‌نام اعضا ---------------- */

    case 'auth-check':
        $id = mb_substr(trim((string) ($_POST['identifier'] ?? '')), 0, 190);
        $type = identifier_type($id);
        if (!$type) json_out(['ok' => false, 'error' => 'یک شماره موبایل، ایمیل یا نام کاربری معتبر وارد کنید.'], 422);
        // جلوگیری از بررسی انبوه شناسه‌ها
        $_SESSION['auth_checks'] = array_values(array_filter($_SESSION['auth_checks'] ?? [], fn($t) => $t > time() - 600));
        if (count($_SESSION['auth_checks']) >= 30) json_out(['ok' => false, 'error' => 'تعداد درخواست‌ها زیاد است؛ چند دقیقه دیگر تلاش کنید.'], 429);
        $_SESSION['auth_checks'][] = time();
        $m = find_member($id);
        if ($m && $m['status'] !== 'active') json_out(['ok' => false, 'error' => 'این حساب مسدود شده است. با پشتیبانی تماس بگیرید.'], 403);
        if (!$m && !setting_on('members_register')) json_out(['ok' => false, 'error' => 'حسابی با این مشخصات پیدا نشد و ثبت‌نام در حال حاضر غیرفعال است.'], 403);
        json_out([
            'ok' => true,
            'exists' => (bool) $m,
            'type' => $type,
            'value' => $type === 'phone' ? normalize_phone($id) : $id,
            'first_name' => $m['first_name'] ?? null,
        ]);

    case 'auth-login':
        if ($wait = member_login_blocked()) json_out(['ok' => false, 'error' => 'به دلیل تلاش‌های ناموفق، ' . fa_num((string) ceil($wait / 60)) . ' دقیقه دیگر تلاش کنید.'], 429);
        $m = find_member((string) ($_POST['identifier'] ?? ''));
        if (!$m || !password_verify((string) ($_POST['password'] ?? ''), $m['password'])) {
            member_login_failed();
            json_out(['ok' => false, 'error' => 'رمز عبور اشتباه است.', 'field' => 'password'], 422);
        }
        if ($m['status'] !== 'active') json_out(['ok' => false, 'error' => 'این حساب مسدود شده است.'], 403);
        q('DELETE FROM login_attempts WHERE ip = ?', ['m:' . client_ip()]);
        member_login($m);
        json_out(['ok' => true, 'name' => $m['first_name'], 'code' => $m['member_code'], 'redirect' => after_login_url(), 'csrf' => csrf_token()]);

    /* ---------- raffles ---------- */
    case 'raffle-state':
    case 'raffle-join':
    case 'raffle-buy':
        if (!setting_on('footer_raffle_link') && !current_user()) json_out(['ok' => false, 'error' => 'بخش قرعه‌کشی فعلاً بسته است.'], 410);
        $r = row('SELECT * FROM raffles WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        if (!$r || ($r['status'] === 'draft' && !current_user())) json_out(['ok' => false, 'error' => 'قرعه‌کشی پیدا نشد.'], 404);
        $act = $seg[1];
        $me = current_member();
        if (!raffle_page_allowed($r, $me)) json_out(['ok' => false, 'error' => 'این قرعه‌کشی تموم شده و صفحه‌ش فقط برای برنده‌ها بازه.'], 403);
        if ($act !== 'raffle-state' && !$me) {
            $_SESSION['after_login'] = 'raffle/' . $r['slug'];
            json_out(['ok' => false, 'login' => true, 'url' => url('auth'), 'error' => 'برای شرکت اول وارد حسابت شو یا ثبت‌نام کن.'], 401);
        }
        if ($act === 'raffle-join') {
            $res = raffle_join($r, $me, (string) ($_SESSION['raffle_ref'][(int) $r['id']] ?? ''));
            if (!$res['ok']) json_out($res, 422);
        } elseif ($act === 'raffle-buy') {
            if (time() - (int) ($_SESSION['last_raffle_buy'] ?? 0) < 15) json_out(['ok' => false, 'error' => 'چند ثانیه صبر کن و دوباره بفرست.'], 429);
            $res = raffle_request_tickets($r, $me, (int) en_num((string) ($_POST['qty'] ?? '0')), $_FILES['receipt'] ?? []);
            if (!$res['ok']) json_out($res, 422);
            $_SESSION['last_raffle_buy'] = time();
        } else {
            $r = raffle_maybe_draw($r);
        }
        $r = row('SELECT * FROM raffles WHERE id = ?', [$r['id']]);
        json_out(['ok' => true] + raffle_state($r, $me));

    /* ---------- lucky wheel ---------- */
    case 'wheel-state':
    case 'wheel-spin':
        if (!setting_on('footer_wheel_link') && !current_user()) json_out(['ok' => false, 'error' => 'بخش گردونه شانس فعلاً بسته است.'], 410);
        $w = row("SELECT * FROM wheels WHERE id = ? AND audience = 'public'", [(int) ($_POST['id'] ?? 0)]);
        if (!$w || ($w['status'] !== 'active' && !current_user())) json_out(['ok' => false, 'error' => 'گردونه پیدا نشد.'], 404);
        $me = current_member();
        if ($seg[1] === 'wheel-spin') {
            if (!$me) {
                $_SESSION['after_login'] = 'wheel/' . $w['slug'];
                json_out(['ok' => false, 'login' => true, 'url' => url('auth'), 'error' => 'برای چرخوندن اول وارد حسابت شو یا ثبت‌نام کن.'], 401);
            }
            if (microtime(true) - (float) ($_SESSION['last_wheel_spin'] ?? 0) < 4) json_out(['ok' => false, 'error' => 'گردونه هنوز داره می‌چرخه…'], 429);
            wheel_track_ref($w, $me);
            $res = wheel_spin($w, $me);
            if (!$res['ok']) json_out($res, 422);
            $_SESSION['last_wheel_spin'] = microtime(true);
            $w = row('SELECT * FROM wheels WHERE id = ?', [$w['id']]);
            json_out($res + ['state' => wheel_state($w, $me)]);
        }
        wheel_track_ref($w, $me);
        json_out(['ok' => true] + wheel_state($w, $me));

    case 'auth-register':
        if (!setting_on('members_register')) json_out(['ok' => false, 'error' => 'ثبت‌نام در حال حاضر غیرفعال است.'], 403);
        if (!empty($_POST['website'])) json_out(['ok' => false, 'error' => 'درخواست نامعتبر.'], 422); // honeypot
        if (time() - (int) ($_SESSION['last_register'] ?? 0) < 60) json_out(['ok' => false, 'error' => 'لطفاً یک دقیقه بعد دوباره تلاش کنید.'], 429);
        [$d, $err] = validate_member_input($_POST);
        if ($err) json_out(['ok' => false, 'errors' => $err, 'error' => reset($err)], 422);
        $code = new_member_code();
        $mid = insert('members', [
            'member_code' => $code, 'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'phone' => $d['phone'],
            'email' => $d['email'], 'username' => $d['username'], 'password' => password_hash($d['password'], PASSWORD_DEFAULT),
            'status' => 'active', 'last_ip' => client_ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $_SESSION['last_register'] = time();
        // دکمه «ورود» بعد از ثبت‌نام بدون وارد کردن دوباره رمز وارد می‌کند (تا ۱۰ دقیقه)
        $_SESSION['pending_mid'] = ['id' => $mid, 'until' => time() + 600];
        json_out(['ok' => true, 'code' => $code, 'name' => $d['first_name'], 'full_name' => $d['first_name'] . ' ' . $d['last_name'], 'username' => $d['username'], 'date' => jdate('j F Y')]);

    case 'auth-finish':
        $p = $_SESSION['pending_mid'] ?? null;
        if (!$p || $p['until'] < time()) json_out(['ok' => false, 'error' => 'زمان ورود خودکار گذشته است؛ لطفاً با رمز عبور وارد شوید.'], 403);
        $m = row("SELECT * FROM members WHERE id = ? AND status = 'active'", [(int) $p['id']]);
        if (!$m) json_out(['ok' => false, 'error' => 'حساب یافت نشد.'], 404);
        member_login($m);
        json_out(['ok' => true, 'name' => $m['first_name'], 'code' => $m['member_code'], 'redirect' => after_login_url(), 'csrf' => csrf_token()]);

    case 'auth-logout':
        member_logout();
        json_out(['ok' => true, 'redirect' => url('')]);

    default:
        json_out(['ok' => false, 'error' => 'not found'], 404);
}
