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
        if (input('position') === 'popup') $html = '<div class="ad-popup-box">' . $html . '</div>';
        json_out(['ok' => true, 'html' => $html]);

    case 'telegram_test':
        if (!can('*')) json_out(['ok' => false], 403);
        $r = telegram_api('getMe', []);
        if (!empty($r['ok'])) {
            $chat = telegram_api('getChat', ['chat_id' => (string) setting('tg_chat_id')]);
            json_out(['ok' => true, 'message' => 'ربات @' . $r['result']['username'] . ' متصل است.' . (!empty($chat['ok']) ? ' کانال: ' . ($chat['result']['title'] ?? '') : ' (دسترسی به کانال: ' . ($chat['description'] ?? 'نامشخص') . ')')]);
        }
        json_out(['ok' => false, 'error' => $r['description'] ?? 'خطا']);

    case 'slug':
        json_out(['ok' => true, 'slug' => slugify((string) input('text'))]);

    default:
        json_out(['ok' => false, 'error' => 'unknown action'], 404);
}
