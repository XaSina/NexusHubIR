<?php
/**
 * اعضای سایت (کاربران عادی): ثبت‌نام، ورود، کد عضویت یکتا
 * جدا از کاربران پنل مدیریت (جدول users)
 */

const NX_DB_VERSION = 19;   // v19: product SEO fields + custom panel roles (per-user permissions)
// v13 note:   // v13: personal invite codes   // v12: public promo codes   // v10: lucky wheel tables (created by schema.php)

/** ارتقای خودکار پایگاه داده برای نصب‌های قبلی (بدون نیاز به نصب مجدد) */
function nx_migrate(): void
{
    try {
        $v = (int) val("SELECT svalue FROM settings WHERE skey = 'db_version'");
    } catch (Throwable $e) {
        return;
    }
    if ($v >= NX_DB_VERSION) return;
    foreach (require NX_APP . '/schema.php' as $sql) db()->exec($sql); // همه CREATE TABLE IF NOT EXISTS هستند
    // v3: per-category visibility (desktop header / mobile menu / footer).
    // Checked via information_schema: MySQL 8 has no "ADD COLUMN IF NOT EXISTS".
    $cols = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories'"), 'COLUMN_NAME');
    foreach (['show_in_header' => 0, 'show_in_mobile' => 1, 'show_in_footer' => 1] as $col => $def) {
        if (!in_array($col, $cols, true)) db()->exec("ALTER TABLE `categories` ADD COLUMN `$col` TINYINT(1) NOT NULL DEFAULT $def");
    }
    // v4: categories already linked in the manual menu start with the desktop-header switch ON, so the
    // switch reflects what is visible (the switch now also controls those manual links)
    if ($v < 4) {
        $menu = json_decode((string) val("SELECT svalue FROM settings WHERE skey = 'menu'"), true);
        if (!is_array($menu)) $menu = json_decode(default_settings()['menu'], true) ?: [];
        foreach ($menu as $m) {
            if (preg_match('~(?:^|/)category/([^/?#]+)/?$~u', (string) parse_url(rawurldecode((string) ($m['url'] ?? '')), PHP_URL_PATH), $mm)) {
                q('UPDATE categories SET show_in_header = 1 WHERE slug = ?', [$mm[1]]);
            }
        }
    }
    // v5: the RSS feed was removed entirely
    if ($v < 5) q("DELETE FROM settings WHERE skey = 'show_rss_link'");
    // v6: shop — its home banner joins an existing custom home layout (switched on)
    if ($v < 6) {
        $hs = json_decode((string) val("SELECT svalue FROM settings WHERE skey = 'home_sections'"), true);
        if (is_array($hs) && !in_array('shop', array_column($hs, 'key'), true)) {
            $hs[] = ['key' => 'shop', 'on' => 1];
            save_settings(['home_sections' => json_encode($hs, JSON_UNESCAPED_UNICODE)]);
        }
        private_dir();
    }
    if ($v < 7) {
        // v7: the default admin display name becomes the brand name
        q("UPDATE users SET display_name = 'NexusHub' WHERE display_name IN ('مدیر NEXUS', 'مدیر nexus')");
    }
    if ($v < 9) {
        // v9: how long a finished raffle keeps its result box on the site
        $rc = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raffles'"), 'COLUMN_NAME');
        if ($rc && !in_array('result_hours', $rc, true)) db()->exec("ALTER TABLE `raffles` ADD COLUMN `result_hours` INT UNSIGNED NOT NULL DEFAULT 24 AFTER `box_accent`");
    }
    if ($v < 11) {
        // v11: prizes + coupons (tables from schema.php); orders remember the discount they got
        $oc = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'"), 'COLUMN_NAME');
        if ($oc && !in_array('discount', $oc, true)) db()->exec("ALTER TABLE `orders` ADD COLUMN `discount` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `total`");
        if ($oc && !in_array('coupon_id', $oc, true)) db()->exec("ALTER TABLE `orders` ADD COLUMN `coupon_id` INT UNSIGNED NULL AFTER `discount`");
        try { prizes_backfill(); } catch (Throwable $e) {}
    }
    if ($v < 12) {
        $oc = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'"), 'COLUMN_NAME');
        if ($oc && !in_array('promo_id', $oc, true)) db()->exec("ALTER TABLE `orders` ADD COLUMN `promo_id` INT UNSIGNED NULL AFTER `coupon_id`");
    }
    if ($v < 13) {
        $mc = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members'"), 'COLUMN_NAME');
        if ($mc && !in_array('invite_code', $mc, true)) db()->exec("ALTER TABLE `members` ADD COLUMN `invite_code` VARCHAR(12) NULL AFTER `member_code`, ADD UNIQUE KEY `u_m_invite` (`invite_code`)");
        if ($mc && !in_array('invited_by', $mc, true)) db()->exec("ALTER TABLE `members` ADD COLUMN `invited_by` INT UNSIGNED NULL AFTER `invite_code`");
    }
    if ($v < 14) {
        $own = (string) setting('admin_gate_key');
        if ($own !== '' && setting_on('admin_gate_on')) save_settings(['admin_gate_hash' => hash('sha256', $own)]);    // keep a link the owner already uses
        else save_settings(['admin_gate_hash' => '15b38d4cd2c8968b5b986394aeda319deadd3a470e324365a69cf798920e4e82', 'admin_gate_on' => '1']);
        save_settings(['admin_gate_key' => '']);                                            // the address itself is never stored
    }
    if ($v < 17) {
        // v17: an ad can be shown in several places (`places`, comma list); `position` stays the first one
        $ac = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ads'"), 'COLUMN_NAME');
        if ($ac && !in_array('places', $ac, true)) db()->exec("ALTER TABLE `ads` ADD COLUMN `places` VARCHAR(500) NULL AFTER `position`");
        q("UPDATE ads SET places = position WHERE places IS NULL OR places = ''");
    }
    if ($v < 18) {
        // v18: sell anything — units & limits, delivery type (manual / post / codes / link), gallery, specs, shipping on orders
        $add = function (string $t, array $cols) {
            $have = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$t]), 'COLUMN_NAME');
            if (!$have) return;
            foreach ($cols as $c => $def) if (!in_array($c, $have, true)) db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        };
        $add('products', ['unit' => 'VARCHAR(30) NULL', 'min_qty' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'max_qty' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'delivery' => "VARCHAR(10) NOT NULL DEFAULT 'manual'", 'delivery_data' => 'MEDIUMTEXT NULL', 'ship_extra' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'gallery' => 'TEXT NULL', 'specs' => 'TEXT NULL']);
        $add('orders', ['ship_cost' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0', 'ship_info' => 'TEXT NULL', 'tracking' => 'VARCHAR(120) NULL']);
        $add('order_items', ['delivered' => 'TEXT NULL']);
    }
    if ($v < 19) {
        // v19: per-product SEO (title / description / keywords for Google) and hand-picked panel permissions for a «custom» role
        $add = function (string $t, array $cols) {
            $have = array_column(rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$t]), 'COLUMN_NAME');
            if (!$have) return;
            foreach ($cols as $c => $def) if (!in_array($c, $have, true)) db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        };
        $add('products', ['seo_title' => 'VARCHAR(190) NULL', 'seo_desc' => 'VARCHAR(320) NULL', 'seo_keywords' => 'VARCHAR(300) NULL']);
        $add('users', ['perms' => 'TEXT NULL']);
    }
    save_settings(['db_version' => (string) NX_DB_VERSION]);
}

function normalize_phone(string $p): string
{
    $p = preg_replace('~[\s\-().]~', '', en_num(trim($p)));
    if (str_starts_with($p, '+98')) $p = '0' . substr($p, 3);
    elseif (str_starts_with($p, '0098')) $p = '0' . substr($p, 4);
    elseif (preg_match('~^9\d{9}$~', $p)) $p = '0' . $p;
    return $p;
}

function is_valid_phone(string $p): bool
{
    return (bool) preg_match('~^09\d{9}$~', $p);
}

/** نوع شناسه وارد شده: phone | email | username | null */
function identifier_type(string $id): ?string
{
    $id = trim($id);
    if ($id === '') return null;
    if (str_contains($id, '@')) return filter_var($id, FILTER_VALIDATE_EMAIL) ? 'email' : null;
    if (is_valid_phone(normalize_phone($id))) return 'phone';
    if (preg_match('~^[A-Za-z][A-Za-z0-9_.]{2,29}$~', $id)) return 'username';
    return null;
}

function find_member(string $identifier): ?array
{
    $type = identifier_type($identifier);
    if (!$type) return null;
    $value = $type === 'phone' ? normalize_phone($identifier) : trim($identifier);
    return row("SELECT * FROM members WHERE `$type` = ?", [$value]);
}

function new_member_code(): string
{
    do {
        $code = 'NX-' . random_int(100000, 999999);
    } while ((int) val('SELECT COUNT(*) FROM members WHERE member_code = ?', [$code]) > 0);
    return $code;
}

/* ---------- personal invite codes (separate from the membership code) ---------- */

/** the member's own invite code — made the first time it is needed */
function member_invite_code(array $m): string
{
    if (!empty($m['invite_code'])) return (string) $m['invite_code'];
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($try = 0; $try < 20; $try++) {
        $c = ''; for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)];
        try { if (q('UPDATE members SET invite_code = ? WHERE id = ? AND invite_code IS NULL', [$c, (int) $m['id']])->rowCount()) return $c; } catch (Throwable $e) { continue; }
        $have = (string) val('SELECT invite_code FROM members WHERE id = ?', [(int) $m['id']]);
        if ($have !== '') return $have;
    }
    return (string) $m['member_code'];
}

/** who owns this invite code (old links with a membership code keep working) */
function invite_owner(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '' || !preg_match('~^[A-Z0-9-]{3,20}$~', $code)) return null;
    return row("SELECT * FROM members WHERE (invite_code = ? OR member_code = ?) AND status = 'active' LIMIT 1", [$code, $code]) ?: null;
}

/** the invite link: short, readable; the target is a wheel (w<id>) or a raffle (r<id>) or the site itself */
function invite_link(array $m, string $target = ''): string
{
    return site_url('i/' . member_invite_code($m) . ($target !== '' ? '/' . $target : ''));
}

/** the invite code this visitor arrived with (session first, then the 30-day cookie) */
function invite_pending(): string
{
    $c = (string) ($_SESSION['invite']['code'] ?? ($_COOKIE['nx_inv'] ?? ''));
    return preg_match('~^[A-Za-z0-9-]{3,20}$~', $c) ? strtoupper($c) : '';
}

/* ---------- saved posts (bookmarks) ---------- */
function bookmarks_on(): bool { return setting_on('bookmarks_on'); }

function bookmark_has(int $memberId, int $postId): bool
{
    try { return (bool) val('SELECT 1 FROM bookmarks WHERE member_id = ? AND post_id = ?', [$memberId, $postId]); } catch (Throwable $e) { return false; }
}

/** saves or un-saves a post; returns the new state */
function bookmark_toggle(int $memberId, int $postId): bool
{
    if (bookmark_has($memberId, $postId)) { q('DELETE FROM bookmarks WHERE member_id = ? AND post_id = ?', [$memberId, $postId]); return false; }
    if ((int) val('SELECT COUNT(*) FROM bookmarks WHERE member_id = ?', [$memberId]) >= 500) {            // keep the list sane
        q('DELETE FROM bookmarks WHERE member_id = ? ORDER BY created_at ASC LIMIT 1', [$memberId]);
    }
    q('INSERT IGNORE INTO bookmarks (member_id, post_id, created_at) VALUES (?, ?, ?)', [$memberId, $postId, now()]);
    return true;
}

/** ids the signed-in member saved (loaded once per page) */
function member_saved_ids(): array
{
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = [];
    if (!bookmarks_on() || !($m = current_member())) return $ids;
    try { foreach (rows('SELECT post_id FROM bookmarks WHERE member_id = ?', [(int) $m['id']]) as $r) $ids[(int) $r['post_id']] = true; } catch (Throwable $e) { }
    return $ids;
}

/** the little save button on cards and posts */
function bookmark_btn(int $postId, string $cls = 'bm-btn', bool $label = false): string
{
    if (!bookmarks_on()) return '';
    $on = isset(member_saved_ids()[$postId]);
    $svg = '<svg class="bm-ic" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4.2-6.5 4.2v-16a1 1 0 0 1 1-1Z"/></svg>';
    return '<button type="button" class="' . e($cls) . ($on ? ' on' : '') . '" data-bm="' . $postId . '" aria-pressed="' . ($on ? 'true' : 'false') . '" title="' . ($on ? 'برداشتن از ذخیره‌شده‌ها' : 'ذخیره برای بعد') . '" aria-label="ذخیره خبر">'
        . $svg . ($label ? '<span class="bm-t" data-on="ذخیره شد" data-off="ذخیره">' . ($on ? 'ذخیره شد' : 'ذخیره') . '</span>' : '') . '</button>';
}

function bookmarks_count(int $memberId): int
{
    try { return (int) val('SELECT COUNT(*) FROM bookmarks b JOIN posts p ON p.id = b.post_id WHERE b.member_id = ? AND ' . pub_where(), [$memberId]); } catch (Throwable $e) { return 0; }
}

function member_full_name(array $m): string
{
    return trim($m['first_name'] . ' ' . $m['last_name']);
}

function current_member(): ?array
{
    static $m = false;
    if ($m !== false) return $m;
    $m = null;
    if (!empty($_SESSION['mid'])) {
        try {
            $r = row("SELECT * FROM members WHERE id = ? AND status = 'active'", [(int) $_SESSION['mid']]);
        } catch (Throwable $e) {
            $r = null;
        }
        if ($r && hash_equals((string) ($_SESSION['mpw'] ?? ''), substr(hash('sha256', $r['password']), 0, 32))) $m = $r;
        else unset($_SESSION['mid'], $_SESSION['mpw']);
    }
    return $m;
}

function member_login(array $m): void
{
    session_regenerate_id(true);
    $_SESSION['mid'] = (int) $m['id'];
    $_SESSION['mpw'] = substr(hash('sha256', $m['password']), 0, 32);
    unset($_SESSION['pending_mid']);
    q('UPDATE members SET login_count = login_count + 1, last_login = ?, last_ip = ? WHERE id = ?', [now(), client_ip(), $m['id']]);
}

function member_logout(): void
{
    unset($_SESSION['mid'], $_SESSION['mpw'], $_SESSION['pending_mid']);
    session_regenerate_id(true);
}

/** محدودیت تلاش ناموفق ورود اعضا: ۸ بار در ۱۵ دقیقه */
function member_login_blocked(): int
{
    $r = row('SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?', ['m:' . client_ip()]);
    if (!$r) return 0;
    $wait = 15 * 60 - (time() - strtotime($r['last_attempt']));
    if ($wait <= 0) { q('DELETE FROM login_attempts WHERE ip = ?', ['m:' . client_ip()]); return 0; }
    return (int) $r['attempts'] >= 8 ? $wait : 0;
}

function member_login_failed(): void
{
    q('INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = VALUES(last_attempt)', ['m:' . client_ip(), now()]);
}

/**
 * اعتبارسنجی اطلاعات ثبت‌نام / ویرایش پروفایل
 * @return array [data, errors(field => message)]
 */
function validate_member_input(array $in, int $exceptId = 0, bool $withPassword = true): array
{
    $d = [
        'first_name' => mb_substr(trim((string) ($in['first_name'] ?? '')), 0, 60),
        'last_name' => mb_substr(trim((string) ($in['last_name'] ?? '')), 0, 60),
        'phone' => normalize_phone((string) ($in['phone'] ?? '')),
        'email' => mb_strtolower(trim((string) ($in['email'] ?? ''))),
        'username' => trim((string) ($in['username'] ?? '')),
    ];
    $err = [];
    if (mb_strlen($d['first_name']) < 2) $err['first_name'] = 'نام را وارد کنید.';
    if (mb_strlen($d['last_name']) < 2) $err['last_name'] = 'نام خانوادگی را وارد کنید.';
    if (!is_valid_phone($d['phone'])) $err['phone'] = 'شماره موبایل معتبر نیست (مثال: 09121234567).';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'ایمیل معتبر نیست.';
    if (!preg_match('~^[A-Za-z][A-Za-z0-9_.]{2,29}$~', $d['username'])) $err['username'] = 'نام کاربری باید ۳ تا ۳۰ کاراکتر انگلیسی باشد و با حرف شروع شود.';
    foreach (['phone' => 'این شماره قبلاً ثبت شده است.', 'email' => 'این ایمیل قبلاً ثبت شده است.', 'username' => 'این نام کاربری قبلاً گرفته شده است.'] as $f => $msg) {
        if (!isset($err[$f]) && (int) val("SELECT COUNT(*) FROM members WHERE `$f` = ? AND id <> ?", [$d[$f], $exceptId]) > 0) $err[$f] = $msg;
    }
    if ($withPassword) {
        $pw = (string) ($in['password'] ?? '');
        if (strlen($pw) < 8) $err['password'] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
        elseif (!preg_match('~[A-Za-z]~', $pw) || !preg_match('~\d~', en_num($pw))) $err['password'] = 'رمز عبور باید شامل حروف انگلیسی و عدد باشد.';
        $d['password'] = $pw;
    }
    return [$d, $err];
}

function member_days(array $m): int
{
    return max(1, (int) floor((time() - strtotime($m['created_at'])) / 86400) + 1);
}
