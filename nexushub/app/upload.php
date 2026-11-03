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

/**
 * Chunked upload (large videos up to 500 MB) — works regardless of the host's upload_max_filesize,
 * because every request carries only one small piece. Pieces must arrive in order.
 */
function upload_chunk(array $f, string $uid, int $index, int $total, string $name, int $maxBytes): array
{
    $mimes = ['video/mp4' => 'mp4', 'video/x-m4v' => 'mp4', 'video/webm' => 'webm'];
    if (!preg_match('~^[a-f0-9]{16,40}$~', $uid)) return ['ok' => false, 'error' => 'شناسه آپلود نامعتبر است.'];
    if ($total < 1 || $total > 5000 || $index < 0 || $index >= $total) return ['ok' => false, 'error' => 'قطعه نامعتبر است.'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'ارسال قطعه ناموفق بود (کد ' . ($f['error'] ?? '?') . ').'];

    $dir = NX_ROOT . '/uploads/.chunks';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return ['ok' => false, 'error' => 'پوشه‌ی uploads قابل نوشتن نیست.'];
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    if (random_int(1, 20) === 1) foreach (glob($dir . '/*.part') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);

    $part = $dir . '/' . $uid . '.part';
    $expected = (int) ($_SESSION['chunks'][$uid] ?? 0);
    if ($index !== $expected) return ['ok' => false, 'error' => 'ترتیب قطعه‌ها به هم خورد؛ دوباره آپلود کنید.'];
    if ($index === 0) {
        @unlink($part);
        // reject non-videos on the very first piece (file type is read from the content, not the name)
        $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $m = $fi ? (string) finfo_file($fi, $f['tmp_name']) : '';
        if ($fi) finfo_close($fi);
        if ($m !== '' && !isset($mimes[$m])) return ['ok' => false, 'error' => 'فقط ویدیوهای MP4 و WebM مجاز هستند' . ($m === 'video/quicktime' ? ' (فایل MOV آیفون را به MP4 تبدیل کنید).' : '.')];
    }
    $in = fopen($f['tmp_name'], 'rb');
    $out = fopen($part, $index === 0 ? 'wb' : 'ab');
    if (!$in || !$out) return ['ok' => false, 'error' => 'ذخیره قطعه ناموفق بود.'];
    stream_copy_to_stream($in, $out);
    fclose($in); fclose($out);
    clearstatcache(true, $part);
    if (filesize($part) > $maxBytes) {
        @unlink($part); unset($_SESSION['chunks'][$uid]);
        return ['ok' => false, 'error' => 'حداکثر حجم ویدیو ' . fa_num((string) round($maxBytes / 1048576)) . ' مگابایت است.'];
    }
    $_SESSION['chunks'][$uid] = $index + 1;
    if ($index < $total - 1) return ['ok' => true, 'next' => $index + 1];

    // last piece: verify the assembled file and move it into the media library
    unset($_SESSION['chunks'][$uid]);
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $fi ? (string) finfo_file($fi, $part) : 'video/mp4';
    if ($fi) finfo_close($fi);
    if (!isset($mimes[$mime])) { @unlink($part); return ['ok' => false, 'error' => 'فایل ویدیوی معتبر MP4/WebM نیست.']; }
    $sub = 'uploads/' . date('Y/m');
    if (!is_dir(NX_ROOT . '/' . $sub) && !@mkdir(NX_ROOT . '/' . $sub, 0755, true)) return ['ok' => false, 'error' => 'پوشه‌ی uploads قابل نوشتن نیست.'];
    $file = date('His') . '-' . bin2hex(random_bytes(6)) . '.' . $mimes[$mime];
    if (!@rename($part, NX_ROOT . '/' . $sub . '/' . $file)) { @unlink($part); return ['ok' => false, 'error' => 'انتقال فایل ناموفق بود.']; }
    @chmod(NX_ROOT . '/' . $sub . '/' . $file, 0644);
    $path = $sub . '/' . $file;
    $size = (int) filesize(NX_ROOT . '/' . $path);
    $id = insert('media', [
        'path' => $path, 'original_name' => mb_substr($name, 0, 250), 'mime' => $mime === 'video/x-m4v' ? 'video/mp4' : $mime,
        'size' => $size, 'uploaded_by' => current_user()['id'] ?? null, 'created_at' => now(),
    ]);
    return ['ok' => true, 'done' => true, 'id' => $id, 'path' => $path, 'url' => media_url($path), 'type' => 'video', 'size' => $size,
        'name' => preg_replace('~\.[a-z0-9]+$~i', '', mb_substr($name, 0, 100))];
}
