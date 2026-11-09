<?php
/**
 * درخواست‌های Ajax پنل مدیریت
 */
require __DIR__ . '/inc/boot.php';

if (!is_post() || !csrf_valid()) json_out(['ok' => false, 'error' => 'نشست منقضی شده؛ صفحه را رفرش کنید.'], 403);

switch ($_POST['action'] ?? '') {

    case 'upload':
        if (!can('media') && !can('posts')) json_out(['ok' => false, 'error' => 'دسترسی ندارید.'], 403);
        if (empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'فایلی ارسال نشد.'], 422);
        json_out(upload_image($_FILES['file']));

    case 'upload_story':
        if (!can('*')) json_out(['ok' => false, 'error' => 'دسترسی ندارید.'], 403);
        if (empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'فایلی ارسال نشد (شاید حجم فایل از حد مجاز سرور بیشتر است).'], 422);
        json_out(upload_story_media($_FILES['file']));

    case 'cmd_search':
        // quick search for the command palette: posts, orders, members, products, tickets, promo codes, raffles, wheels
        $raw = trim(en_num((string) ($_POST['q'] ?? '')));
        if (mb_strlen($raw) < 2) json_out(['ok' => true, 'items' => []]);
        $like = '%' . addcslashes(mb_substr($raw, 0, 60), '%_\\') . '%';
        $out = [];
        $add = function (string $grp, string $icon, string $title, string $sub, string $href) use (&$out) {
            $out[] = ['g' => $grp, 'i' => $icon, 't' => $title, 's' => $sub, 'h' => url('admin/' . $href)];
        };
        $try = function (callable $fn) { try { $fn(); } catch (Throwable $e) { } };
        if (can('posts')) $try(function () use ($like, $add) {
            $own = can('edit_others') ? '' : ' AND author_id = ' . (int) current_user()['id'];
            foreach (rows("SELECT id, title, status, published_at FROM posts WHERE title LIKE ?$own ORDER BY id DESC LIMIT 6", [$like]) as $r)
                $add('اخبار', '📰', (string) $r['title'], $r['status'] === 'published' ? 'منتشر شده' : ($r['status'] === 'scheduled' ? 'زمان‌بندی' : 'پیش‌نویس'), 'post-edit.php?id=' . $r['id']);
        });
        if (can('shop')) {
            $try(function () use ($like, $add) {
                foreach (rows("SELECT o.id, o.code, o.status, o.total, m.first_name, m.last_name FROM orders o LEFT JOIN members m ON m.id = o.member_id
                    WHERE o.code LIKE ? OR o.receipt_ref LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.phone LIKE ? ORDER BY o.id DESC LIMIT 6", [$like, $like, $like, $like]) as $r)
                    $add('سفارش‌ها', '🧾', 'سفارش ' . $r['code'], trim($r['first_name'] . ' ' . $r['last_name']) . ' · ' . fa_num(number_format((int) $r['total'])) . ' ت', 'order-view.php?id=' . $r['id']);
            });
            $try(function () use ($like, $add) {
                foreach (rows('SELECT id, title FROM products WHERE title LIKE ? ORDER BY id DESC LIMIT 5', [$like]) as $r)
                    $add('محصولات', '🛍️', (string) $r['title'], 'ویرایش محصول', 'product-edit.php?id=' . $r['id']);
            });
            $try(function () use ($like, $add) {
                foreach (rows('SELECT id, code, subject, status FROM tickets WHERE subject LIKE ? OR code LIKE ? ORDER BY id DESC LIMIT 4', [$like, $like]) as $r)
                    $add('تیکت‌ها', '🎫', (string) $r['subject'], 'تیکت ' . $r['code'] . ($r['status'] === 'open' ? ' · باز' : ''), 'ticket-view.php?id=' . $r['id']);
            });
        }
        if (can('*')) {
            $try(function () use ($like, $add) {
                foreach (rows("SELECT id, member_code, first_name, last_name, phone, username FROM members
                    WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ? OR member_code LIKE ? OR username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 6", [$like, $like, $like, $like, $like]) as $r)
                    $add('اعضا', '👤', trim($r['first_name'] . ' ' . $r['last_name']), fa_num((string) $r['phone']) . ' · ' . $r['member_code'], 'member-view.php?id=' . $r['id']);
            });
            $try(function () use ($like, $add) {
                foreach (rows('SELECT id, code, pct FROM promo_codes WHERE code LIKE ? OR title LIKE ? ORDER BY id DESC LIMIT 4', [$like, $like]) as $r)
                    $add('کدهای تخفیف', '🏷️', (string) $r['code'], fa_num(rtrim(rtrim((string) $r['pct'], '0'), '.')) . '٪ تخفیف', 'promos.php');
            });
            $try(function () use ($like, $add) {
                foreach (rows('SELECT id, title, status FROM raffles WHERE title LIKE ? ORDER BY id DESC LIMIT 4', [$like]) as $r)
                    $add('قرعه‌کشی', '🎁', (string) $r['title'], 'مدیریت قرعه‌کشی', 'raffle-view.php?id=' . $r['id']);
                foreach (rows('SELECT id, title FROM wheels WHERE title LIKE ? ORDER BY id DESC LIMIT 4', [$like]) as $r)
                    $add('گردونه شانس', '🎡', (string) $r['title'], 'مدیریت گردونه', 'wheel-view.php?id=' . $r['id']);
            });
        }
        json_out(['ok' => true, 'items' => $out]);

    case 'online':
        [$t, $m] = online_count();
        json_out(['ok' => true, 'total' => $t, 'members' => $m]);

    case 'upload_chunk':
        // kind: post (news video, 500 MB) | story (story video, 100 MB)
        $kind = (string) ($_POST['kind'] ?? 'post');
        if ($kind === 'story' ? !can('*') : !(can('posts') || can('media'))) json_out(['ok' => false, 'error' => 'دسترسی ندارید.'], 403);
        if (empty($_FILES['chunk'])) json_out(['ok' => false, 'error' => 'قطعه‌ای دریافت نشد.'], 422);
        @set_time_limit(120);
        json_out(upload_chunk($_FILES['chunk'], (string) ($_POST['uid'] ?? ''), (int) ($_POST['index'] ?? -1), (int) ($_POST['total'] ?? 0),
            (string) ($_POST['name'] ?? 'video'), $kind === 'story' ? 100 * 1048576 : 500 * 1048576));

    case 'media_list':
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $per = 40;
        $off = ($page - 1) * $per;
        // the picker is used for images (covers, logos, editor): never offer videos there
        $items = rows("SELECT * FROM media WHERE mime IS NULL OR mime LIKE 'image/%' ORDER BY id DESC LIMIT $per OFFSET $off");
        $total = (int) val("SELECT COUNT(*) FROM media WHERE mime IS NULL OR mime LIKE 'image/%'");
        json_out([
            'ok' => true,
            'items' => array_map(fn($m) => ['id' => (int) $m['id'], 'path' => $m['path'], 'url' => media_url($m['path']), 'w' => $m['width'], 'h' => $m['height']], $items),
            'more' => $off + $per < $total,
        ]);

    case 'ad_preview':
        if (!can('*')) json_out(['ok' => false], 403);
        $a = [
            'id' => 0, 'title' => (string) input('title'), 'format' => (string) input('format'), 'theme' => (string) input('theme'),
            'accent' => (string) input('accent'), 'image' => (string) input('image'), 'link' => (string) input('link'),
            'html' => (string) ($_POST['html'] ?? ''), 'heading' => (string) input('heading'), 'body' => (string) input('body'),
            'button_text' => (string) input('button_text'), 'emoji' => (string) input('emoji'), 'badge' => (string) input('badge'),
            'device' => 'all', 'countdown' => (int) input('countdown', 0), 'new_tab' => 1,
            'end_at' => jalali_to_datetime((string) input('end_at')) ?: date('Y-m-d H:i:s', strtotime('+3 days')),
        ];
        $html = render_ad($a, true);
        if (in_array('popup', (array) ($_POST['places'] ?? []), true) && count((array) $_POST['places']) === 1) $html = '<div class="nxp-popup-box">' . $html . '</div>';
        json_out(['ok' => true, 'html' => $html]);

    case 'telegram_test':
        if (!can('*')) json_out(['ok' => false], 403);
        $r = telegram_api('getMe', []);
        if (!empty($r['ok'])) {
            $chat = telegram_api('getChat', ['chat_id' => (string) setting('tg_chat_id')]);
            json_out(['ok' => true, 'message' => 'ربات @' . $r['result']['username'] . ' متصل است.' . (!empty($chat['ok']) ? ' کانال: ' . ($chat['result']['title'] ?? '') : ' (دسترسی به کانال: ' . ($chat['description'] ?? 'نامشخص') . ')')]);
        }
        json_out(['ok' => false, 'error' => $r['description'] ?? 'خطا']);

    case 'admin_notifs':
        if (!can('shop')) json_out(['ok' => false], 403);
        if (!empty($_POST['read'])) q("UPDATE notifications SET is_read = 1 WHERE audience = 'admin'");
        $items = array_map(function ($n) { $j = notif_json($n); $j['link'] = url((string) $n['link']); return $j; }, notifs_for('admin', 30));
        $latest = row("SELECT * FROM notifications WHERE audience = 'admin' AND is_read = 0 ORDER BY updated_at DESC LIMIT 1");
        json_out(['ok' => true, 'items' => $items, 'unread' => notifs_unread('admin'), 'latest' => $latest ? notif_json($latest) : null,
            'review' => (int) val("SELECT COUNT(*) FROM orders WHERE status IN ('reviewing','approved')"), 'tickets' => (int) val("SELECT COUNT(*) FROM tickets WHERE status = 'open'")]);

    case 'rates_test':
        if (!can('*')) json_out(['ok' => false], 403);
        $lines = [];
        $logT = []; [$ton] = fetch_ton_usd($logT);
        foreach ($logT as [$n, $ok, $v, $ms]) $lines[] = ($ok ? '✅ ' : '❌ ') . 'TON — ' . $n . ($ok ? ': $' . number_format((float) $v, 3) : ': پاسخ نداد') . ' (' . fa_num((string) $ms) . 'ms)';
        $logU = []; [$usd] = fetch_usdt_toman($logU);
        foreach ($logU as [$n, $ok, $v, $ms]) $lines[] = ($ok ? '✅ ' : '❌ ') . 'تتر — ' . $n . ($ok ? ': ' . money((int) $v) . ' تومان' : ': پاسخ نداد') . ' (' . fa_num((string) $ms) . 'ms)';
        if (!$usd) $lines[] = 'ℹ️ هیچ صرافی ایرانی به هاست شما جواب نداد (معمولاً یعنی هاست خارج از ایران است)؛ حالت «دستی» را برای دلار نگه دارید.';
        $r = shop_rates(true);
        json_out(['ok' => true, 'lines' => $lines, 'ton' => $r['ton'], 'usd' => $r['usd'], 'ton_src' => $r['ton_src'], 'usd_src' => $r['usd_src']]);

    case 'market_test':   // checks every price source on its own, then refreshes the board
        if (!can('*')) json_out(['ok' => false], 403);
        $sel = market_selected(); $A = market_assets();
        $coins = array_values(array_unique(array_merge(array_filter($sel, fn($k) => ($A[$k][3] ?? '') === 'coin'), array_intersect($sel, ['gold18', 'ounce']) ? ['paxg'] : [])));
        $lines = [];
        if ($coins) foreach (['Nobitex', 'CoinGecko', 'KuCoin'] as $src) {
            $lg = []; $t = microtime(true); [$got] = fetch_coins_usd($coins, $lg, [$src]);
            $ms = (int) round((microtime(true) - $t) * 1000);
            $lines[] = ($got ? '✅ ' : '❌ ') . 'ارز دیجیتال — ' . $src . ': ' . ($got ? fa_num((string) count($got)) . ' از ' . fa_num((string) count($coins)) . ' قیمت' : 'پاسخ نداد') . ' (' . fa_num((string) $ms) . 'ms)';
        }
        if (array_intersect($sel, ['eur', 'gbp', 'try', 'aed'])) {
            $lg = []; fetch_fiat_rates($lg);
            foreach ($lg as [$n, $ok, $cnt, $ms]) $lines[] = ($ok ? '✅ ' : '❌ ') . 'ارزها — ' . $n . ($ok ? ': ' . fa_num((string) $cnt) . ' نرخ' : ': پاسخ نداد') . ' (' . fa_num((string) $ms) . 'ms)';
        }
        $lg = []; [$usd] = fetch_usdt_toman($lg);
        $first = $lg ? end($lg) : null;
        $lines[] = ($usd ? '✅ ' : '❌ ') . 'دلار (تتر): ' . ($usd ? money((int) $usd) . ' تومان — ' . $first[0] : 'هیچ صرافی ایرانی جواب نداد') . (setting('shop_usd_mode') !== 'auto' ? ' · ℹ️ حالت دلار «دستی» است؛ قیمت‌های تومانی با نرخ دستی حساب می‌شوند' : '');
        // refresh the board now with fresh values
        $c = json_decode((string) setting('shop_market_cache'), true) ?: []; unset($c['forced_at'], $c['coins_fail'], $c['fiat_fail']); save_settings(['shop_market_cache' => json_encode($c)]);
        settings_all(true);
        $b = market_board(true, true);
        $rows = array_map(fn($it) => ['icon' => $it['icon'], 'name' => $it['name'], 'fa' => $it['toman'] ? money($it['toman']) . ' تومان' : '—', 'usd' => $it['usd'] !== null ? '$' . market_usd($it['usd']) : '—', 'chg' => $it['chg']], $b['items']);
        json_out(['ok' => true, 'lines' => $lines, 'rows' => $rows]);

    case 'slug':
        json_out(['ok' => true, 'slug' => slugify((string) input('text'))]);

    default:
        json_out(['ok' => false, 'error' => 'unknown action'], 404);
}
