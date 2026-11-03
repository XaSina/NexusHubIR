<?php
/**
 * ارسال خودکار خبر به کانال تلگرام با ربات
 * (ربات باید ادمین کانال باشد)
 */

function telegram_api(string $method, array $params): array
{
    $token = trim((string) setting('tg_bot_token'));
    if ($token === '') return ['ok' => false, 'description' => 'توکن ربات تنظیم نشده است.'];
    if (!function_exists('curl_init')) return ['ok' => false, 'description' => 'افزونه cURL روی سرور فعال نیست.'];
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'عدم اتصال به تلگرام: ' . $err];
    $j = json_decode((string) $res, true);
    return is_array($j) ? $j : ['ok' => false, 'description' => 'پاسخ نامعتبر از تلگرام'];
}

function telegram_send_post(array $post): array
{
    $chat = trim((string) setting('tg_chat_id'));
    if ($chat === '') return ['ok' => false, 'description' => 'شناسه کانال تنظیم نشده است.'];

    $tpl = (string) setting('tg_template', "<b>{title}</b>\n\n{excerpt}\n\n🔗 {link}");
    $excerpt = trim(strip_shortcodes((string) $post['excerpt'])) ?: str_excerpt((string) $post['content'], 300);
    $text = strtr($tpl, [
        '{title}' => htmlspecialchars($post['title'], ENT_NOQUOTES),
        '{excerpt}' => htmlspecialchars($excerpt, ENT_NOQUOTES),
        '{link}' => site_url('post/' . rawurlencode($post['slug'])),
        '{channel}' => telegram_handle(),
        '{category}' => htmlspecialchars((string) ($post['cat_name'] ?? ''), ENT_NOQUOTES),
    ]);

    $coverFile = !empty($post['cover']) && str_starts_with($post['cover'], 'uploads/') ? NX_ROOT . '/' . $post['cover'] : '';
    if ($coverFile && is_file($coverFile)) {
        if (mb_strlen($text) > 1000) $text = mb_substr($text, 0, 1000) . '…';
        return telegram_api('sendPhoto', [
            'chat_id' => $chat,
            'photo' => new CURLFile($coverFile),
            'caption' => $text,
            'parse_mode' => 'HTML',
        ]);
    }
    return telegram_api('sendMessage', ['chat_id' => $chat, 'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML']);
}
