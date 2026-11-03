<?php
/**
 * آپلود امن تصاویر
 */

function upload_image(array $file, int $maxWidth = 1920): array
{
    if (!isset($file['error']) || is_array($file['error'])) return ['ok' => false, 'error' => 'فایل نامعتبر است.'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز سرور است.',
            UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشده است.',
            default => 'خطا در آپلود فایل (کد ' . $file['error'] . ')',
        };
        return ['ok' => false, 'error' => $msg];
    }
    if ($file['size'] > 10 * 1024 * 1024) return ['ok' => false, 'error' => 'حداکثر حجم مجاز ۱۰ مگابایت است.'];

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) return ['ok' => false, 'error' => 'فایل انتخابی تصویر معتبر نیست.'];
    if ($mime === '') $mime = $info['mime'];
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) return ['ok' => false, 'error' => 'فقط فرمت‌های JPG، PNG، GIF و WEBP مجاز هستند.'];

    $sub = 'uploads/' . date('Y/m');
    $dir = NX_ROOT . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'پوشه‌ی uploads قابل نوشتن نیست.'];

    $name = date('His') . '-' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        if (!@rename($file['tmp_name'], $dest)) return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
    }
    @chmod($dest, 0644);

    [$w, $h] = $info;
    // کوچک کردن تصاویر خیلی بزرگ
    if ($w > $maxWidth && $mime !== 'image/gif' && function_exists('imagecreatetruecolor')) {
        $resized = resize_image($dest, $mime, $maxWidth);
        if ($resized) [$w, $h] = $resized;
        clearstatcache(true, $dest);
    }

    $path = $sub . '/' . $name;
    $id = insert('media', [
        'path' => $path,
        'original_name' => mb_substr((string) $file['name'], 0, 250),
        'mime' => $mime,
        'size' => (int) filesize($dest),
        'width' => $w,
        'height' => $h,
        'uploaded_by' => current_user()['id'] ?? null,
        'created_at' => now(),
    ]);
    return ['ok' => true, 'id' => $id, 'path' => $path, 'url' => media_url($path)];
}

function resize_image(string $path, string $mime, int $maxWidth): ?array
{
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if (!$src) return null;
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = $maxWidth;
    $nh = (int) round($h * $nw / $w);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime !== 'image/jpeg') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match ($mime) {
        'image/jpeg' => imagejpeg($dst, $path, 85),
        'image/png' => imagepng($dst, $path, 8),
        'image/webp' => imagewebp($dst, $path, 85),
    };
    imagedestroy($src);
    imagedestroy($dst);
    return [$nw, $nh];
}

function delete_media(int $id): void
{
    $m = row('SELECT * FROM media WHERE id = ?', [$id]);
    if (!$m) return;
    $file = NX_ROOT . '/' . $m['path'];
    if (str_starts_with($m['path'], 'uploads/') && is_file($file)) @unlink($file);
    q('DELETE FROM media WHERE id = ?', [$id]);
}

/** حداکثر حجم آپلود سرور (بایت) — کمترین مقدار upload_max_filesize و post_max_size */
function server_upload_limit(): int
{
    $toBytes = function ($v) {
        $v = trim((string) $v);
        if ($v === '') return PHP_INT_MAX;
        $n = (float) $v;
        return (int) match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    };
    $a = $toBytes(ini_get('upload_max_filesize'));
    $b = $toBytes(ini_get('post_max_size'));
    $lim = min($a ?: PHP_INT_MAX, $b ?: PHP_INT_MAX);
    return $lim === PHP_INT_MAX ? 0 : $lim;
}

/**
 * آپلود ویدیو برای استوری (MP4 / WebM — فرمت‌هایی که در همه مرورگرهای موبایل پخش می‌شوند)
 */
function upload_video(array $file, int $maxBytes = 60 * 1024 * 1024): array
{
    if (!isset($file['error']) || is_array($file['error'])) return ['ok' => false, 'error' => 'فایل نامعتبر است.'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'حجم ویدیو بیش از حد مجاز سرور است (' . fa_num((string) round(server_upload_limit() / 1048576)) . ' مگابایت).'
            : 'خطا در آپلود فایل (کد ' . $file['error'] . ')'];
    }
    if ($file['size'] > $maxBytes) return ['ok' => false, 'error' => 'حداکثر حجم ویدیو ' . fa_num((string) round($maxBytes / 1048576)) . ' مگابایت است.'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    }
    $exts = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!isset($exts[$mime])) return ['ok' => false, 'error' => 'فقط ویدیوهای MP4 و WebM مجاز هستند.'];

    $sub = 'uploads/' . date('Y/m');
    $dir = NX_ROOT . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'پوشه‌ی uploads قابل نوشتن نیست.'];
    $name = date('His') . '-' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
    @chmod($dir . '/' . $name, 0644);
    $path = $sub . '/' . $name;
    $id = insert('media', [
        'path' => $path, 'original_name' => mb_substr((string) $file['name'], 0, 250), 'mime' => $mime,
        'size' => (int) filesize($dir . '/' . $name), 'width' => null, 'height' => null,
        'uploaded_by' => current_user()['id'] ?? null, 'created_at' => now(),
    ]);
    return ['ok' => true, 'id' => $id, 'path' => $path, 'url' => media_url($path), 'type' => 'video'];
}

/** آپلود رسانه‌ی استوری: تصویر یا ویدیو بر اساس محتوای واقعی فایل */
function upload_story_media(array $file): array
{
    $mime = '';
    if (!empty($file['tmp_name']) && is_file($file['tmp_name']) && function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    }
    if (str_starts_with($mime, 'video/')) return upload_video($file);
    $r = upload_image($file, 1440);
    if ($r['ok']) $r['type'] = 'image';
    return $r;
}
