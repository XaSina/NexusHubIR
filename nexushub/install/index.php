<?php
/**
 * NEXUS | HUB — نصب‌کننده
 */
declare(strict_types=1);
define('NX_ROOT', dirname(__DIR__));
define('NX_APP', NX_ROOT . '/app');
define('NX_VERSION', '1.0.0');
date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');

require NX_APP . '/jdate.php';
require NX_APP . '/helpers.php';

session_name('nxinstall');
session_start();

$locked = is_file(NX_APP . '/installed.lock');
$errors = [];
$done = false;

$req = [
    'PHP نسخه 8.1 یا بالاتر' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'افزونه PDO MySQL' => extension_loaded('pdo_mysql'),
    'افزونه mbstring' => extension_loaded('mbstring'),
    'افزونه GD (پردازش تصویر)' => extension_loaded('gd'),
    'افزونه DOM (پاکسازی HTML)' => class_exists('DOMDocument'),
    'افزونه cURL (ارسال به تلگرام)' => function_exists('curl_init'),
    'پوشه app قابل نوشتن' => is_writable(NX_APP),
    'پوشه uploads قابل نوشتن' => is_writable(NX_ROOT . '/uploads'),
];
$critical = ['PHP نسخه 8.1 یا بالاتر', 'افزونه PDO MySQL', 'افزونه mbstring', 'پوشه app قابل نوشتن', 'پوشه uploads قابل نوشتن'];
$canInstall = true;
foreach ($critical as $c) if (!$req[$c]) $canInstall = false;

$f = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => 'nexushub', 'db_user' => 'root', 'db_pass' => '',
    'site_title' => 'NEXUS | HUB', 'admin_user' => 'admin', 'admin_name' => 'NexusHub', 'admin_email' => '', 'admin_pass' => '', 'demo' => '1',
];

if (!$locked && $canInstall && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($f as $k => $v) $f[$k] = trim((string) ($_POST[$k] ?? ($k === 'demo' ? '0' : '')));
    if (!preg_match('~^[A-Za-z0-9_.\-]{3,40}$~', $f['admin_user'])) $errors[] = 'نام کاربری مدیر باید ۳ تا ۴۰ کاراکتر انگلیسی باشد.';
    if (strlen($f['admin_pass']) < 8) $errors[] = 'رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.';
    if ($f['db_name'] === '' || !preg_match('~^[A-Za-z0-9_\-]+$~', $f['db_name'])) $errors[] = 'نام پایگاه داده معتبر نیست.';

    if (!$errors) {
        try {
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            try {
                $pdo = new PDO("mysql:host={$f['db_host']};port=" . (int) $f['db_port'] . ";dbname={$f['db_name']};charset=utf8mb4", $f['db_user'], $f['db_pass'], $opts);
            } catch (PDOException $e) {
                // تلاش برای ساخت دیتابیس (در XAMPP)
                $pdo = new PDO("mysql:host={$f['db_host']};port=" . (int) $f['db_port'] . ";charset=utf8mb4", $f['db_user'], $f['db_pass'], $opts);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$f['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            unset($pdo);

            $config = "<?php\n// تنظیمات اتصال — توسط نصب‌کننده ساخته شد\nreturn " . var_export([
                'db_host' => $f['db_host'],
                'db_port' => (int) $f['db_port'],
                'db_name' => $f['db_name'],
                'db_user' => $f['db_user'],
                'db_pass' => $f['db_pass'],
                'site_url' => '', // خالی = تشخیص خودکار. مثال: https://nexushub.ir
                'secret' => bin2hex(random_bytes(24)),
                'debug' => false,
            ], true) . ";\n";
            if (file_put_contents(NX_APP . '/config.php', $config) === false) throw new RuntimeException('امکان ساخت فایل app/config.php وجود ندارد.');

            require NX_APP . '/db.php';
            require NX_APP . '/defaults.php';
            require NX_APP . '/settings.php';
            require NX_APP . '/auth.php';
            require NX_APP . '/content.php';
            require NX_APP . '/seed.php';

            foreach (require NX_APP . '/schema.php' as $sql) db()->exec($sql);

            if ((int) val('SELECT COUNT(*) FROM users') > 0) {
                throw new RuntimeException('این پایگاه داده قبلاً نصب شده است (کاربر وجود دارد). یک دیتابیس خالی انتخاب کنید یا جداول قبلی را حذف کنید.');
            }

            db()->beginTransaction();
            $uid = insert('users', [
                'username' => $f['admin_user'], 'display_name' => $f['admin_name'] ?: $f['admin_user'], 'email' => $f['admin_email'],
                'password' => password_hash($f['admin_pass'], PASSWORD_DEFAULT), 'role' => 'admin', 'is_active' => 1, 'created_at' => now(),
            ]);
            $defaults = default_settings();
            $defaults['site_title'] = $f['site_title'] ?: 'NEXUS | HUB';
            $defaults['db_version'] = '4';
            save_settings($defaults);
            if ($f['demo'] === '1') seed_demo($uid);
            db()->commit();

            file_put_contents(NX_APP . '/installed.lock', date('c'));
            $done = true;
        } catch (Throwable $e) {
            if (function_exists('db') && is_file(NX_APP . '/config.php')) { try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $x) {} }
            if (!$done && is_file(NX_APP . '/config.php') && !is_file(NX_APP . '/installed.lock')) @unlink(NX_APP . '/config.php');
            $msg = $e->getMessage();
            if ($e instanceof PDOException) $msg = 'خطای پایگاه داده: ' . $msg;
            $errors[] = $msg;
        }
    }
}
$base = nx_base_path();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>نصب NEXUS | HUB</title>
<link rel="icon" href="<?= e($base) ?>/assets/img/favicon.png">
<style>
@font-face{font-family:Vazirmatn;src:url(<?= e($base) ?>/assets/fonts/Vazirmatn-Variable.woff2) format("woff2");font-weight:100 900}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Vazirmatn,Tahoma;background:#050507;color:#f4f4f7;display:grid;place-items:center;padding:24px 16px;overflow-x:hidden}
body::before{content:"";position:fixed;width:60vmax;height:60vmax;top:-25vmax;right:-20vmax;border-radius:50%;background:radial-gradient(circle,rgba(255,31,61,.45),transparent 65%);filter:blur(60px);z-index:-1;animation:f 14s ease-in-out infinite alternate}
body::after{content:"";position:fixed;width:50vmax;height:50vmax;bottom:-25vmax;left:-20vmax;border-radius:50%;background:radial-gradient(circle,rgba(255,122,61,.3),transparent 65%);filter:blur(60px);z-index:-1;animation:f 18s ease-in-out infinite alternate-reverse}
@keyframes f{to{transform:translate(-10vw,10vh)}}
.box{width:min(760px,100%);background:rgba(20,20,28,.6);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.09);border-radius:26px;padding:34px;box-shadow:0 30px 80px rgba(0,0,0,.6)}
.head{display:flex;align-items:center;gap:16px;margin-bottom:24px}.head img{width:70px;height:70px;border-radius:50%;box-shadow:0 0 0 3px #ff1f3d,0 0 40px rgba(255,31,61,.6)}
h1{margin:0;font-size:1.5rem}.head p{margin:2px 0 0;color:#9d9dab;font-size:.9rem}
h3{margin:26px 0 12px;font-size:1rem;color:#ff4d65;display:flex;align-items:center;gap:8px}h3::after{content:"";flex:1;height:1px;background:rgba(255,255,255,.08)}
.req{display:grid;grid-template-columns:1fr 1fr;gap:8px}.req span{font-size:.84rem;padding:8px 12px;border-radius:12px;background:rgba(255,255,255,.04)}.ok{color:#3ddc84}.no{color:#ff5a6e}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}label{display:flex;flex-direction:column;gap:6px;font-size:.85rem;color:#b9b9c6}
input{padding:12px 14px;border-radius:13px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#fff;font:inherit;outline:0;transition:.3s}input:focus{border-color:#ff1f3d;box-shadow:0 0 0 4px rgba(255,31,61,.15)}
.ltr{direction:ltr;text-align:left}.hint{font-size:.78rem;color:#7d7d8b;margin-top:-4px}
.btn{display:inline-flex;align-items:center;gap:8px;justify-content:center;border:0;cursor:pointer;padding:14px 28px;border-radius:15px;font:inherit;font-weight:800;color:#fff;background:linear-gradient(135deg,#ff1f3d,#ff7a3d);box-shadow:0 12px 30px -8px rgba(255,31,61,.8);text-decoration:none;transition:.3s}.btn:hover{transform:translateY(-2px)}
.btn.ghost{background:rgba(255,255,255,.06);box-shadow:none;border:1px solid rgba(255,255,255,.12)}
.err{background:rgba(255,90,110,.12);border:1px solid rgba(255,90,110,.4);color:#ff8a98;padding:12px 16px;border-radius:14px;margin-bottom:10px;font-size:.9rem}
.success{text-align:center}.success .big{font-size:4rem}.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.chk{flex-direction:row;align-items:center;gap:10px;margin-top:14px;color:#ddd}.chk input{width:20px;height:20px;accent-color:#ff1f3d}
.warn{background:rgba(255,197,61,.1);border:1px solid rgba(255,197,61,.35);color:#ffd27a;padding:12px 16px;border-radius:14px;font-size:.86rem;margin-top:16px}
@media(max-width:600px){.grid,.req{grid-template-columns:1fr}.box{padding:22px}}
</style>
</head>
<body>
<div class="box">
    <div class="head">
        <img src="<?= e($base) ?>/assets/img/logo-256.png" alt="">
        <div><h1>نصب NEXUS | HUB</h1><p>سایت خبری حرفه‌ای — نسخه <?= NX_VERSION ?></p></div>
    </div>

<?php if ($locked && !$done): ?>
    <div class="success">
        <div class="big">🔒</div>
        <h2>سایت قبلاً نصب شده است</h2>
        <p style="color:#9d9dab">برای امنیت بیشتر، پوشه <b>install</b> را از روی هاست حذف کنید.<br>برای نصب مجدد، فایل <code>app/installed.lock</code> و <code>app/config.php</code> را حذف کنید.</p>
        <div class="actions"><a class="btn" href="<?= e($base) ?>/">مشاهده سایت</a><a class="btn ghost" href="<?= e($base) ?>/admin/">پنل مدیریت</a></div>
    </div>
<?php elseif ($done): ?>
    <div class="success">
        <div class="big">🎉</div>
        <h2>نصب با موفقیت انجام شد!</h2>
        <p style="color:#9d9dab">نام کاربری: <b class="ltr"><?= e($f['admin_user']) ?></b></p>
        <div class="warn">⚠️ پوشه <b>install</b> را بعد از نصب از روی هاست حذف کنید (دسترسی مجدد به آن قفل شده است).</div>
        <div class="actions"><a class="btn" href="<?= e($base) ?>/admin/">ورود به پنل مدیریت</a><a class="btn ghost" href="<?= e($base) ?>/">مشاهده سایت</a></div>
    </div>
<?php else: ?>
    <h3>بررسی پیش‌نیازها</h3>
    <div class="req">
        <?php foreach ($req as $k => $ok): ?><span class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✔' : '✖' ?> <?= e($k) ?></span><?php endforeach; ?>
    </div>
    <?php if (!$canInstall): ?><div class="err" style="margin-top:14px">برخی پیش‌نیازهای ضروری فراهم نیست. لطفاً آن‌ها را برطرف کنید.</div><?php endif; ?>

    <form method="post" autocomplete="off">
        <?php foreach ($errors as $er): ?><div class="err" style="margin-top:14px"><?= e($er) ?></div><?php endforeach; ?>
        <h3>🗄️ اتصال به پایگاه داده</h3>
        <div class="grid">
            <label>هاست دیتابیس<input class="ltr" name="db_host" value="<?= e($f['db_host']) ?>" required></label>
            <label>پورت<input class="ltr" name="db_port" value="<?= e($f['db_port']) ?>" required></label>
            <label>نام دیتابیس<input class="ltr" name="db_name" value="<?= e($f['db_name']) ?>" required></label>
            <label>نام کاربری دیتابیس<input class="ltr" name="db_user" value="<?= e($f['db_user']) ?>" required></label>
            <label>رمز دیتابیس<input class="ltr" type="password" name="db_pass" value="<?= e($f['db_pass']) ?>"></label>
        </div>
        <p class="hint">در XAMPP معمولاً: هاست localhost، کاربر root و رمز خالی. در cPanel ابتدا از بخش MySQL Databases دیتابیس و کاربر بسازید.</p>

        <h3>👑 حساب مدیر و سایت</h3>
        <div class="grid">
            <label>عنوان سایت<input name="site_title" value="<?= e($f['site_title']) ?>" required></label>
            <label>نام نمایشی مدیر<input name="admin_name" value="<?= e($f['admin_name']) ?>"></label>
            <label>نام کاربری مدیر (انگلیسی)<input class="ltr" name="admin_user" value="<?= e($f['admin_user']) ?>" required></label>
            <label>ایمیل مدیر<input class="ltr" type="email" name="admin_email" value="<?= e($f['admin_email']) ?>"></label>
            <label>رمز عبور مدیر (حداقل ۸ کاراکتر)<input class="ltr" type="password" name="admin_pass" required minlength="8"></label>
        </div>
        <label class="chk"><input type="checkbox" name="demo" value="1" <?= $f['demo'] === '1' ? 'checked' : '' ?>> نصب محتوای نمونه (اخبار، دسته‌ها و تبلیغات نمونه)</label>
        <div style="margin-top:24px"><button class="btn" type="submit" <?= $canInstall ? '' : 'disabled' ?>>🚀 نصب کن</button></div>
    </form>
<?php endif; ?>
</div>
<script src="<?= e($base) ?>/assets/js/pw.js"></script>
</body>
</html>
