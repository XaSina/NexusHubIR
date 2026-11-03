<?php
/**
 * احراز هویت و سطوح دسترسی
 */

function role_names(): array
{
    return ['admin' => 'مدیر کل', 'editor' => 'سردبیر', 'author' => 'نویسنده'];
}

function role_caps(): array
{
    return [
        'admin' => ['*'],
        'editor' => ['posts', 'edit_others', 'categories', 'comments', 'media', 'pages', 'messages', 'shop'],
        'author' => ['posts', 'media'],
    ];
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $u = row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['uid']]);
        if ($u && hash_equals((string) ($_SESSION['upw'] ?? ''), substr(hash('sha256', $u['password']), 0, 32))) {
            $user = $u;
        } else {
            unset($_SESSION['uid'], $_SESSION['upw']);
        }
    }
    return $user;
}

function can(string $cap): bool
{
    $u = current_user();
    if (!$u) return false;
    $caps = role_caps()[$u['role']] ?? [];
    return in_array('*', $caps, true) || in_array($cap, $caps, true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('admin/login.php');
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
        q('UPDATE users SET last_login = ? WHERE id = ?', [now(), $u['id']]);
        q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
        return true;
    }
    q('INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = VALUES(last_attempt)', [client_ip(), now()]);
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
