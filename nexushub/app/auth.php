<?php
/**
 * احراز هویت و سطوح دسترسی
 */

function role_names(): array
{
    return ['admin' => 'مدیر کل', 'editor' => 'سردبیر', 'author' => 'نویسنده', 'custom' => 'دسترسی دستی (انتخابی)'];
}

/** every section of the panel that can be ticked for a «custom» user, grouped for the checklist */
function perm_groups(): array
{
    return [
        '📰 محتوا' => [
            'posts' => 'نوشتن و ویرایش خبرهای خودش', 'edit_others' => 'ویرایش و حذف خبرهای بقیه', 'categories' => 'دسته‌بندی‌های اخبار',
            'comments' => 'تأیید و حذف نظرات', 'media' => 'کتابخانه رسانه و آپلود', 'stories' => 'استوری‌ها', 'pages' => 'صفحه‌ها (درباره ما، قوانین…)', 'messages' => 'پیام‌های تماس با ما',
        ],
        '🛍️ فروشگاه' => [
            'orders' => 'سفارش‌ها (تأیید فیش، تحویل، رهگیری)', 'products' => 'محصولات و دسته‌های فروشگاه', 'tickets' => 'تیکت‌های پشتیبانی',
            'reports' => 'گزارش فروش ماهانه', 'shop_settings' => 'تنظیمات فروشگاه (پرداخت، ارسال، سئو…)',
        ],
        '🎁 بازاریابی' => [
            'promos' => 'کدهای تخفیف', 'raffles' => 'قرعه‌کشی‌ها', 'wheels' => 'گردونه‌های شانس', 'prizes' => 'جایزه‌ها و برنده‌ها', 'ads' => 'تبلیغات',
        ],
        '⚙️ سایت و مدیریت' => [
            'members' => 'اعضای سایت', 'appearance' => 'ظاهر سایت و صفحه اصلی', 'settings' => 'تنظیمات کلی سایت', 'security' => 'امنیت و لینک ورود پنل',
            'backup' => 'پشتیبان‌گیری و دانلود فایل‌ها', 'users' => 'کاربران پنل (فقط غیر از مدیر کل)',
        ],
    ];
}

function perm_list(): array
{
    return array_merge(...array_values(perm_groups()));
}

function role_caps(): array
{
    return [
        'admin' => ['*'],
        'editor' => ['posts', 'edit_others', 'categories', 'comments', 'media', 'pages', 'messages', 'orders', 'products', 'tickets', 'reports'],
        'author' => ['posts', 'media'],
    ];
}

/** what a panel user may do: the role's set, or the hand-picked list of a «custom» user */
function user_caps(?array $u): array
{
    if (!$u) return [];
    if (($u['role'] ?? '') === 'custom') {
        $p = json_decode((string) ($u['perms'] ?? ''), true);
        $caps = array_values(array_intersect(is_array($p) ? $p : [], array_keys(perm_list())));
    } else $caps = role_caps()[$u['role']] ?? [];
    // «shop» = can see any part of the shop (bell, nav group, month close)
    if (array_intersect(['orders', 'products', 'tickets', 'reports', 'shop_settings'], $caps)) $caps[] = 'shop';
    return $caps;
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['uid']]);
        $idle = max(10, (int) setting('admin_idle_min', 120)) * 60;
        $uaOk = hash_equals((string) ($_SESSION['uua'] ?? ''), substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 24));
        $fresh = time() - (int) ($_SESSION['uat'] ?? 0) < $idle;
        if ($u && $uaOk && $fresh && hash_equals((string) ($_SESSION['upw'] ?? ''), substr(hash('sha256', $u['password']), 0, 32))) {
            $user = $u;
            $_SESSION['uat'] = time();
        } else {
            unset($_SESSION['uid'], $_SESSION['upw'], $_SESSION['uua'], $_SESSION['uat']);
        }
    }
    return $user;
}

function can(string $cap): bool
{
    $u = current_user();
    if (!$u) return false;
    $caps = user_caps($u);
    return in_array('*', $caps, true) || in_array($cap, $caps, true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (admin_gate_on()) admin_gate_hide();
        redirect('admin/login.php');
    }
    return $u;
}

function require_cap(string $cap): void
{
    require_login();
    if (!can($cap)) {
        http_response_code(403);
        flash('شما به این بخش دسترسی ندارید.', 'error');
        redirect('admin/');
    }
}

function login_blocked(): int
{
    $r = row('SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?', [client_ip()]);
    if (!$r) return 0;
    $wait = 15 * 60 - (time() - strtotime($r['last_attempt']));
    if ((int) $r['attempts'] >= 5 && $wait > 0) return $wait;
    if ($wait <= 0) q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    return 0;
}

function attempt_login(string $username, string $password): bool
{
    $u = row('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1', [$username, $username]);
    if ($u && password_verify($password, $u['password'])) {
        session_regenerate_id(true);
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $u['password'] = password_hash($password, PASSWORD_DEFAULT);
            q('UPDATE users SET password = ? WHERE id = ?', [$u['password'], $u['id']]);
        }
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['upw'] = substr(hash('sha256', $u['password']), 0, 32);
        $_SESSION['uua'] = substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 24);
        $_SESSION['uat'] = time();
        unset($_SESSION['gate']);
        admin_login_log($u, true);
        q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
        q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
        return true;
    }
    q('INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = VALUES(last_attempt)', [client_ip(), now()]);
    if ($u) { rate_hit('alogin-acct', 10, 1800, 'u' . $u['id']); admin_login_log($u, false); }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function user_avatar(?array $u, int $size = 40): string
{
    if ($u && !empty($u['avatar'])) {
        return '<img class="avatar" src="' . e(media_url($u['avatar'])) . '" width="' . $size . '" height="' . $size . '" alt="">';
    }
    $name = $u['display_name'] ?? ($u['username'] ?? '?');
    $ch = mb_substr($name !== '' ? $name : '?', 0, 1);
    return '<span class="avatar avatar-letter" style="width:' . $size . 'px;height:' . $size . 'px">' . e($ch) . '</span>';
}
