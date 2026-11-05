<?php
/**
 * NEXUS | HUB — هسته‌ی برنامه
 * این فایل در ابتدای همه‌ی صفحات لود می‌شود.
 */
declare(strict_types=1);

define('NX_ROOT', dirname(__DIR__));
define('NX_APP', __DIR__);
define('NX_VERSION', '1.0.0');

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

require NX_APP . '/jdate.php';
require NX_APP . '/helpers.php';

// اگر هنوز نصب نشده، به نصب‌کننده برو
if (!is_file(NX_APP . '/config.php')) {
    header('Location: ' . nx_base_path() . '/install/');
    exit;
}

$__cfg = config();
if (!empty($__cfg['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

require NX_APP . '/db.php';
require NX_APP . '/defaults.php';
require NX_APP . '/settings.php';
require NX_APP . '/auth.php';
require NX_APP . '/sanitize.php';
require NX_APP . '/content.php';
require NX_APP . '/ads.php';
require NX_APP . '/upload.php';
require NX_APP . '/telegram.php';
require NX_APP . '/members.php';
require NX_APP . '/shop.php';
require NX_APP . '/market.php';
require NX_APP . '/seo.php';

nx_session_start();

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    error_log('[NexusHub] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = (bool) config('debug');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>خطا</title>'
        . '<body style="background:#050507;color:#eee;font-family:tahoma;padding:40px;text-align:center">'
        . '<h2 style="color:#ff1f3d">خطایی رخ داد</h2><p>لطفاً کمی بعد دوباره تلاش کنید.</p>';
    if ($debug) {
        echo '<pre style="text-align:left;direction:ltr;background:#111;padding:16px;border-radius:12px;overflow:auto">'
            . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString())
            . '</pre>';
    }
    echo '</body></html>';
});

// ارتقای خودکار ساختار پایگاه داده (برای نصب‌های قبلی)
nx_migrate();
