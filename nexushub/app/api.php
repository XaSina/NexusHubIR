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

    default:
        json_out(['ok' => false, 'error' => 'not found'], 404);
}
