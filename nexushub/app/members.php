<?php
/**
 * اعضای سایت (کاربران عادی): ثبت‌نام، ورود، کد عضویت یکتا
 * جدا از کاربران پنل مدیریت (جدول users)
 */

const NX_DB_VERSION = 8;

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
