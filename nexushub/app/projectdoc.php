<?php
/**
 * Full project export (Panel → Backup): every file of the site in one ZIP, an optional database dump,
 * and a generated README.md that explains the project well enough for another developer or AI to work on it.
 * "Safe" mode leaves out passwords, keys and members' personal data so the ZIP can be shared.
 */

/** settings whose values are secrets */
function export_secret_setting(string $k): bool
{
    return in_array($k, ['tg_bot_token', 'admin_gate_hash', 'admin_gate_key'], true) || (bool) preg_match('~(token|secret|password|passwd|api_?key|_pass)$~i', $k);
}

/** tables that hold people's personal data / activity (safe mode keeps only their structure) */
function export_private_tables(): array
{
    return ['members', 'orders', 'order_items', 'tickets', 'ticket_messages', 'messages', 'comments', 'login_attempts', 'notifications', 'online',
        'bookmarks', 'promo_uses', 'raffle_entries', 'raffle_requests', 'wheel_spins', 'wheel_refs', 'wheel_grants', 'prizes', 'prize_messages',
        'coupons', 'reaction_log', 'sales_months', 'stats_daily'];
}

/** writes an SQL dump to a stream; $safe strips secrets and personal rows */
function sql_dump_to($fh, bool $safe = false, string $createDb = ''): void
{
    @set_time_limit(300);
    $pdo = db();
    $tables = array_map(fn($r) => array_values($r)[0], rows('SHOW TABLES'));
    fwrite($fh, "-- NEXUS | HUB database\n-- " . jdate('Y/m/d H:i', null, false) . ($safe ? "\n-- SAFE COPY: secrets removed, personal data tables exported as structure only" : '') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    if ($createDb !== '') fwrite($fh, "CREATE DATABASE IF NOT EXISTS `$createDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nUSE `$createDb`;\n\n");
    $private = array_flip(export_private_tables());
    foreach ($tables as $t) {
        $t = str_replace('`', '', $t);
        $create = row('SHOW CREATE TABLE `' . $t . '`');
        // MySQL 8 collations don't exist in XAMPP's MariaDB: use the portable one
        $ddl = preg_replace('~utf8mb4_0900_[a-z_]+~', 'utf8mb4_unicode_ci', (string) array_values($create)[1]);
        fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n" . $ddl . ";\n\n");
        if ($safe && isset($private[$t])) { fwrite($fh, "-- rows of `$t` left out (personal data)\n\n"); continue; }
        $cols = array_column(rows("SHOW COLUMNS FROM `$t`"), 'Field');
        $st = $pdo->query("SELECT * FROM `$t`");
        $batch = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($safe) {
                if ($t === 'settings' && export_secret_setting((string) $r['skey'])) $r['svalue'] = '';
                if ($t === 'users') { $r['password'] = ''; if (isset($r['email'])) $r['email'] = 'user' . $r['id'] . '@example.com'; }
            }
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($r))) . ')';
            if (count($batch) >= 100) { fwrite($fh, "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
        }
        if ($batch) fwrite($fh, "INSERT INTO `$t` (`" . implode('`,`', $cols) . "`) VALUES\n" . implode(",\n", $batch) . ";\n");
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
}

/** should this project file go into the ZIP? */
function export_take(string $rel, array $o): bool
{
    $rel = str_replace('\\', '/', $rel);
    if (preg_match('~(^|/)\.(git|svn|idea|vscode|DS_Store)(/|$)~', $rel)) return false;
    if (preg_match('~^(node_modules|dist|vendor/bin)/~', $rel)) return false;
    if (preg_match('~\.(zip|sql|gz|tar|bak|log|tmp)$~i', $rel)) return false;
    if (in_array($rel, ['app/admin-unlock.txt', 'README.md'], true)) return false;           // README.md is generated fresh
    if (str_starts_with($rel, 'app/logs/') && $rel !== 'app/logs/.htaccess' && $rel !== 'app/logs/index.html') return false;
    if (str_starts_with($rel, 'uploads/')) {
        if (in_array($rel, ['uploads/.htaccess', 'uploads/index.html'], true)) return true;
        if (str_starts_with($rel, 'uploads/.chunks/')) return false;
        if (str_starts_with($rel, 'uploads/private/')) return !empty($o['uploads']) && empty($o['safe']);   // payment receipts
        return !empty($o['uploads']);
    }
    if (!empty($o['safe']) && in_array($rel, ['app/config.php', 'app/installed.lock'], true)) return false;
    return true;
}

/** builds the ZIP into a temp file and returns its path */
function project_export_zip(array $o): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('افزونه‌ی ZipArchive روی هاست فعال نیست.');
    @set_time_limit(600);
    @ini_set('memory_limit', '512M');
    $tmp = tempnam(sys_get_temp_dir(), 'nxz');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ساخت فایل ZIP ممکن نشد.');
    $root = rtrim(str_replace('\\', '/', NX_ROOT), '/');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    $count = 0;
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
        if (!export_take($rel, $o)) continue;
        $zip->addFile($f->getPathname(), 'nexushub/' . $rel);
        $count++;
    }
    if (is_file($root . '/README.md')) $zip->addFile($root . '/README.md', 'nexushub/FEATURES-fa.md');
    $zip->addFromString('nexushub/README.md', project_readme($o));
    if (!empty($o['db'])) {
        $fh = fopen('php://temp/maxmemory:' . (8 * 1048576), 'w+');
        sql_dump_to($fh, !empty($o['safe']));
        rewind($fh);
        $zip->addFromString('database/nexushub' . (!empty($o['safe']) ? '-safe' : '') . '.sql', stream_get_contents($fh));
        fclose($fh);
    }
    $zip->setArchiveComment('NEXUS | HUB ' . NX_VERSION . ' · ' . date('Y-m-d H:i') . ' · ' . $count . ' files');
    $zip->close();
    return $tmp;
}

/** a copy that runs on a Windows PC with XAMPP: files + ready config + a database file that creates its own database */
function project_xampp_zip(bool $uploads = true): string
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('افزونه‌ی ZipArchive روی هاست فعال نیست.');
    @set_time_limit(900);
    @ini_set('memory_limit', '512M');
    $tmp = tempnam(sys_get_temp_dir(), 'nxx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ساخت فایل ZIP ممکن نشد.');
    $root = rtrim(str_replace('\\', '/', NX_ROOT), '/');
    $o = ['uploads' => $uploads, 'safe' => false, 'db' => true];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = ltrim(substr(str_replace('\\', '/', $f->getPathname()), strlen($root)), '/');
        if ($rel === 'app/config.php' || !export_take($rel, $o)) continue;
        $zip->addFile($f->getPathname(), 'nexushub/' . $rel);
    }
    if (is_file($root . '/README.md')) $zip->addFile($root . '/README.md', 'nexushub/FEATURES-fa.md');
    $zip->addFromString('nexushub/README.md', project_readme(['db' => true, 'uploads' => $uploads]));
    // config for XAMPP's defaults (MySQL user root, no password); the address is detected by itself
    $cfg = "<?php\n// XAMPP — ساخته‌شده از پنل ← پشتیبان‌گیری. اگه رمز MySQL گذاشتی، db_pass رو پر کن.\nreturn [\n    'db_host' => 'localhost',\n    'db_port' => 3306,\n    'db_name' => 'nexushub',\n    'db_user' => 'root',\n    'db_pass' => '',\n    'site_url' => '',\n    'secret' => '" . bin2hex(random_bytes(24)) . "',\n    'debug' => false,\n];\n";
    $zip->addFromString('nexushub/app/config.php', $cfg);
    if (!is_file($root . '/app/installed.lock')) $zip->addFromString('nexushub/app/installed.lock', date('c'));
    $fh = fopen('php://temp/maxmemory:' . (8 * 1048576), 'w+');
    sql_dump_to($fh, false, 'nexushub');
    rewind($fh);
    $zip->addFromString('nexushub-database.sql', stream_get_contents($fh));
    fclose($fh);
    $zip->addFromString('راهنمای-نصب-XAMPP.txt', xampp_guide());
    $zip->setArchiveComment('NEXUS | HUB ' . NX_VERSION . ' for XAMPP · ' . date('Y-m-d H:i'));
    $zip->close();
    return $tmp;
}

function xampp_guide(): string
{
    $L = [
        'NEXUS | HUB — نصب روی کامپیوتر با XAMPP',
        'ساخته‌شده: ' . jdate('Y/m/d H:i', null, false),
        '',
        '۱) XAMPP رو نصب کن و از XAMPP Control Panel دو تا گزینه‌ی Apache و MySQL رو Start کن.',
        '۲) پوشه‌ی nexushub رو (همونی که کنار این فایله) کپی کن داخل:  C:\\xampp\\htdocs\\',
        '   یعنی آخرش بشه:  C:\\xampp\\htdocs\\nexushub\\index.php',
        '۳) مرورگر رو باز کن و برو به:  http://localhost/phpmyadmin',
        '   بالای صفحه «Import» (درون‌ریزی) رو بزن، فایل nexushub-database.sql رو انتخاب کن و پایین صفحه «Import / Go» رو بزن.',
        '   (این فایل خودش دیتابیس nexushub رو می‌سازه؛ لازم نیست دستی بسازیش.)',
        '۴) سایت آماده‌ست:  http://localhost/nexushub/',
        '',
        'ورود به پنل مدیریت:',
        '   همون لینک مخفی که روی سایت اصلی استفاده می‌کنی، فقط اولش رو عوض کن:',
        '   http://localhost/nexushub/ + همون کد طولانی آخر لینکت',
        '   اگه لینک یادت نیست: یه فایل خالی به اسم admin-unlock.txt داخل C:\\xampp\\htdocs\\nexushub\\app\\ بساز،',
        '   برو به http://localhost/nexushub/admin/login.php و وارد شو، بعد از «امنیت» یه لینک جدید بساز و اون فایل رو پاک کن.',
        '',
        'اگه چیزی کار نکرد:',
        '   • صفحه‌ها «Not Found» میدن → فایل C:\\xampp\\htdocs\\nexushub\\.htaccess رو با Notepad باز کن و جلوی خط «# RewriteBase /nexushub/» علامت # رو پاک کن.',
        '   • خطای اتصال به دیتابیس → اگه برای MySQL رمز گذاشتی، داخل nexushub\\app\\config.php جلوی db_pass بنویسش.',
        '   • پورت 80 گرفته‌ست (Skype/IIS) → از XAMPP ← Config ← httpd.conf پورت رو 8080 کن و برو به http://localhost:8080/nexushub/',
        '',
        'نکته: این نسخه کامله (همه‌ی اعضا، سفارش‌ها و تنظیمات). فقط برای خودت نگهش دار و به کسی نده.',
        'برای دادن به برنامه‌نویس یا هوش مصنوعی از «دانلود ZIP کامل پروژه» با «نسخه‌ی امن» استفاده کن.',
    ];
    return "\xEF\xBB\xBF" . implode("\r\n", $L) . "\r\n";
}

/** one-line notes for the files another developer / AI will look for first */
function project_file_notes(): array
{
    return [
        'index.php' => 'Front controller + router for the public site: `switch ($seg[0])` → `page_out(view, data, meta)`.',
        '.htaccess' => 'Pretty URLs (everything → index.php) and security rules. Keep the security block.',
        'app/bootstrap.php' => 'Loads config, every app/*.php module, starts the session, sends security headers, runs migrations.',
        'app/config.php' => 'Database login + secret. Written by the installer. NEVER commit or share.',
        'app/config.sample.php' => 'Template for config.php.',
        'app/db.php' => 'PDO wrapper: `db()`, `q($sql,$args)`, `row()`, `rows()`, `val()`, `insert($table,$data)`, `update()`. Always prepared statements.',
        'app/schema.php' => 'Every CREATE TABLE (IF NOT EXISTS). Add new tables here.',
        'app/members.php' => '`NX_DB_VERSION` + `nx_migrate()` (versioned upgrades), members, invite codes, saved posts.',
        'app/defaults.php' => '`default_settings()` – every setting key with its default value; order statuses; role caps.',
        'app/settings.php' => '`setting($k)`, `setting_on($k)`, `save_settings([...])`, theme colours → CSS variables.',
        'app/helpers.php' => 'Generic helpers: `e()` (HTML escape), `url()`, `asset()`, `redirect()`, `flash()`, `csrf_*`, `icon()`, `partial()`, `paginate()`.',
        'app/jdate.php' => 'Jalali (Persian) calendar: `jdate()`, `gregorian_to_jalali()`, `jalali_to_gregorian()`, `fa_num()` (Persian digits).',
        'app/auth.php' => 'Panel users: login, roles (`can($cap)`), session hardening.',
        'app/security.php' => 'Security headers, rate limits, captcha, hidden panel entrance (`admin_gate_*`). Sensitive.',
        'app/content.php' => 'News posts queries (`POST_SELECT`, `get_posts()`, `pub_where()`).',
        'app/api.php' => 'Public JSON API (`/api/<action>`), every call needs the CSRF token.',
        'app/shop.php' => 'Shop: products, cart, checkout, orders, receipts, notifications (`notify()`), menus.',
        'app/reports.php' => 'Monthly sales report (Jalali months, auto close into `sales_months`).',
        'app/raffle.php' => 'Raffles (free / referral / paid tickets) and draws.',
        'app/wheel.php' => 'Lucky wheel.',
        'app/prizes.php' => 'Prizes and coupons won in raffles / wheel.',
        'app/promos.php' => 'Public discount codes.',
        'app/ads.php' => 'Ads, promo boxes, top strip.',
        'app/seo.php' => 'Meta tags, structured data, sitemaps, IndexNow.',
        'app/views/layout.php' => 'Site shell: <head>, header, menus, drawer, footer, bell. Every page renders inside it.',
        'app/views/partials/card.php' => 'News card (variants: card, big, row, rank).',
        'admin/inc/boot.php' => 'Panel shell: `adm_head()`, `adm_foot()`, sidebar menu `adm_nav()`, form helpers `f_text()`, `f_switch()`…',
        'admin/ajax.php' => 'Panel AJAX actions (uploads, quick search, tests).',
        'install/index.php' => 'First-time installer (creates config.php and tables). Remove/lock after install.',
        'assets/css/style.css' => 'Main site styles (theme variables come from the panel).',
        'assets/css/admin.css' => 'Panel styles.',
        'assets/js/app.js' => 'Main site script (toasts, API helper `api()`, reactions, comments, saved posts…).',
        'assets/js/admin.js' => 'Main panel script.',
        'assets/js/cmdk.js' => 'Panel quick search (Ctrl + K).',
        'assets/js/btnfx.js' => 'Button feedback everywhere («در حال ذخیره…» → «ذخیره شد ✓»).',
        'assets/js/confirm.js' => 'Themed confirm dialog (`data-confirm`) and password prompt (`data-pw`, `nxPassword()`).',
    ];
}

/** README.md for the ZIP: how the project was built, how to run it, what to change and what never to touch */
function project_readme(array $o = []): string
{
    $root = NX_ROOT;
    $now = jdate('Y/m/d H:i', null, false) . ' (' . date('Y-m-d H:i') . ')';
    $L = [];
    $L[] = '# ' . setting('site_title') . ' — How it was built';
    $L[] = '';
    $L[] = '> این فایل خودکار از «پنل ← پشتیبان‌گیری» ساخته شده تا هر برنامه‌نویس یا هوش مصنوعی بدون سردرگمی بفهمد پروژه چطور ساخته شده، کجا را می‌شود تغییر داد و به چه چیزهایی نباید دست زد.';
    $L[] = '> This file was generated by the site itself. Read it fully before changing anything.';
    $L[] = '';
    $L[] = '| | |';
    $L[] = '|---|---|';
    $L[] = '| Site | ' . setting('site_title') . ' — ' . site_url('') . ' |';
    $L[] = '| Version | ' . NX_VERSION . ' (database schema v' . NX_DB_VERSION . ') |';
    $L[] = '| Exported | ' . $now . ' |';
    $L[] = '| PHP on the server | ' . PHP_VERSION . ' |';
    $L[] = '| ZIP contents | site files' . (!empty($o['db']) ? ' + `database/` SQL dump' : '') . (!empty($o['uploads']) ? ' + uploaded media' : '') . (!empty($o['safe']) ? ' — **safe copy**: no `app/config.php`, no passwords/tokens, no members\' personal data' : ' — **full private backup** (contains secrets: keep it private)') . ' |';
    $L[] = '';
    $L[] = '## Built with';
    $L[] = '';
    $L[] = '- **PHP 8.1+, no framework, no Composer.** Plain PHP files; every module in `app/` is a set of functions loaded by `app/bootstrap.php`.';
    $L[] = '- **MySQL / MariaDB** through PDO (`app/db.php`), utf8mb4, InnoDB. Prepared statements only.';
    $L[] = '- **Vanilla JavaScript** (no jQuery, no build step) and **plain CSS** with CSS variables for the theme.';
    $L[] = '- **Persian (fa-IR), right-to-left** UI everywhere; dates are **Jalali** (`app/jdate.php`); numbers shown with Persian digits (`fa_num()`).';
    $L[] = '- Font: Vazirmatn (local file). Captcha drawn with **GD** using `app/fonts/captcha.ttf`.';
    $L[] = '- Hosting target: shared **cPanel / Apache** with `.htaccess` (also runs on XAMPP).';
    $L[] = '- Telegram bot (optional) for auto-posting news; IndexNow for search engines.';
    $L[] = '';
    $L[] = '## How a request works';
    $L[] = '';
    $L[] = '1. Apache sends every URL to `index.php` (see `.htaccess`). The panel is the `admin/` folder (each page is its own PHP file).';
    $L[] = '2. `index.php` requires `app/bootstrap.php` (config, all modules, session, security headers, `nx_migrate()`).';
    $L[] = '3. The URL is split into `$seg` and `switch ($seg[0])` picks the page. Public routes: `' . implode('`, `', project_routes($root . '/index.php')) . '`.';
    $L[] = '4. A page calls `page_out(\'view-name\', $data, $meta)` → `app/views/<view-name>.php` rendered inside `app/views/layout.php`.';
    $L[] = '5. JavaScript talks to `/api/<action>` (`app/api.php`, POST + CSRF token) — actions: `' . implode('`, `', project_routes($root . '/app/api.php')) . '`.';
    $L[] = '6. Panel pages start with `require __DIR__ . \'/inc/boot.php\'; require_cap(\'...\');`, then `adm_head(title, menuKey)` … HTML … `adm_foot()`.';
    $L[] = '';
    $L[] = '## Folder map';
    $L[] = '';
    $L[] = '```';
    foreach (['admin' => 'panel pages (one file per page) + admin/inc/boot.php', 'app' => 'all PHP logic (modules), config, schema', 'app/views' => 'public page templates', 'app/views/partials' => 'reusable pieces (cards…)', 'assets/css' => 'stylesheets', 'assets/js' => 'scripts', 'assets/img' => 'built-in images, icons, presets', 'assets/fonts' => 'web font', 'install' => 'installer', 'uploads' => 'user uploads (media, receipts in uploads/private – never public)'] as $d => $why) {
        $n = is_dir($root . '/' . $d) ? count(array_filter(scandir($root . '/' . $d), fn($x) => is_file($root . '/' . $d . '/' . $x))) : 0;
        $L[] = str_pad($d . '/', 22) . str_pad('(' . $n . ' files)', 13) . $why;
    }
    $L[] = '```';
    $L[] = '';
    $L[] = '### Key files';
    $L[] = '';
    foreach (project_file_notes() as $f => $note) {
        if (!is_file($root . '/' . $f) && $f !== 'app/config.php') continue;
        $lines = is_file($root . '/' . $f) ? count(file($root . '/' . $f)) : 0;
        if ($f === 'app/config.php' && !empty($o['safe'])) { $L[] = '- `app/config.php` — **not included in this safe ZIP**: create it from `app/config.sample.php`. ' . $note; continue; }
        $L[] = '- `' . $f . '`' . ($lines ? ' (' . $lines . ' lines)' : '') . ' — ' . $note;
    }
    $L[] = '';
    $L[] = '### Panel pages (`admin/`)';
    $L[] = '';
    foreach (adm_nav_for_doc() as [$label, $href]) $L[] = '- `admin/' . $href . '` — ' . $label;
    $L[] = '';
    $L[] = '## Database';
    $L[] = '';
    $L[] = 'All tables are created by `app/schema.php`. Upgrades are versioned in `nx_migrate()` (`app/members.php`); the current version is stored in the `settings` table as `db_version` (now **' . NX_DB_VERSION . '**).';
    $L[] = '';
    $L[] = '| Table | Rows | Columns |';
    $L[] = '|---|---:|---|';
    foreach (rows('SHOW TABLES') as $r) {
        $t = array_values($r)[0];
        try { $cnt = (int) val('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`'); $cols = array_column(rows('SHOW COLUMNS FROM `' . str_replace('`', '', $t) . '`'), 'Field'); } catch (Throwable $e) { continue; }
        $L[] = '| `' . $t . '` | ' . $cnt . ' | ' . implode(', ', $cols) . ' |';
    }
    $L[] = '';
    $L[] = 'Settings are key/value rows in `settings` (' . (int) val('SELECT COUNT(*) FROM settings') . ' keys). Every key and its default lives in `default_settings()` (`app/defaults.php`). Read with `setting(\'key\')` / `setting_on(\'key\')`, write with `save_settings([\'key\' => \'value\'])`.';
    $L[] = '';
    $L[] = '## Instructions';
    $L[] = '';
    $L[] = '### Run it locally (XAMPP) or move it to a new host';
    $L[] = '1. Copy the `nexushub/` folder to the web root (e.g. `htdocs/nexushub` or `public_html`).';
    $L[] = '2. Create an empty MySQL database (utf8mb4).';
    $L[] = !empty($o['db'])
        ? '3. Import `database/' . (!empty($o['safe']) ? 'nexushub-safe.sql' : 'nexushub.sql') . '` with phpMyAdmin.'
        : '3. Open `/install/` in the browser and follow the installer (it creates the tables).';
    $L[] = '4. Copy `app/config.sample.php` to `app/config.php` and fill in the database login, `site_url` and a long random `secret`' . (!empty($o['safe']) ? ' (this safe copy has no config.php on purpose).' : ' (the full backup already has one).');
    $L[] = '5. Make `uploads/` writable (755/775). Apache needs `mod_rewrite`; PHP needs `pdo_mysql`, `mbstring`, `gd`, `zip` (zip only for this export).';
    $L[] = !empty($o['safe'])
        ? '6. In this safe copy all panel passwords were blanked. Create a panel user again (installer, or set `users.password` to `password_hash(\'new-pass\', PASSWORD_DEFAULT)`).'
        : '6. Log in to the panel through the hidden entrance link you already use (see “Never touch”).';
    $L[] = '';
    $L[] = '### Coding rules used everywhere (follow them)';
    $L[] = '- Output: always `<?= e($value) ?>`. Never echo user data raw.';
    $L[] = '- Forms: `<?= csrf_field() ?>` in every POST form, `csrf_check()` on the server; JS sends `_csrf`.';
    $L[] = '- SQL: only `q()/row()/rows()/val()/insert()` with `?` placeholders.';
    $L[] = '- Text shown to people is **Persian**, layout is RTL. Dates via `jdate()`, digits via `fa_num()`, money via `money()` (Toman).';
    $L[] = '- Panel permissions: `require_cap(\'*\')` (owner) or a capability like `shop`, `posts`; check `can()` before showing links.';
    $L[] = '- Styles: reuse the theme variables (`--primary`, `--primary-rgb`, `--grad`, `--a-surface`, `--a-line`…). Boxes must never touch: keep 12–18px gaps, test at 360, 390, 768, 1024, 1280, 1440px.';
    $L[] = '- Buttons: `assets/js/btnfx.js` (loaded on site, panel and both login pages) shows «در حال …» while a button works and «… شد ✓» after a request. It reads the button label; set `data-busy` / `data-done` for custom texts, `data-download` for buttons that return a file, `data-nofb` to switch it off.';
    $L[] = '- Sensitive downloads: add `data-pw` to the form (asks «رمز عبور را وارد کنید», sends `confirm_pw`) and call `adm_pw_check()` on the server before sending the file.';
    $L[] = '- Performance modes (`html.perf-ultra / perf-balanced / perf-lite / perf-off`): heavy effects only in ultra; phones default to lighter modes.';
    $L[] = '';
    $L[] = '## To change it ✅ (safe places)';
    $L[] = '';
    $L[] = '- **Texts, colours, menus, home sections, footer, SEO, ads, themes** → from the panel first (Appearance, Settings, Shop settings). No code needed.';
    $L[] = '- **Home page & sidebar** → Panel → «صفحه اصلی سایت» (`admin/appearance.php?tab=layout`): section order/switches (`home_sections`), section titles & counts (`home_*`), sidebar widgets order/switches (`sidebar_widgets`, rendered by `app/views/partials/sidebar.php`) and their texts (`sb_*`). Header/top-bar switches: `hdr_*`, `topbar_*`.';
    $L[] = '- **Ads** → one ad can be shown in several places: column `ads.places` (comma list, `position` = first place). Wide places wrap their boxes in `.nxp-row` (two per row); see `ad_slot()` in `app/ads.php`.';
    $L[] = '- **A new public page** → add a `case \'name\':` in `index.php` + a view in `app/views/name.php`.';
    $L[] = '- **A new panel page** → copy a small page like `admin/promos.php`, then add it to `adm_nav()` in `admin/inc/boot.php` (label, file, icon, capability, key).';
    $L[] = '- **A new setting** → add the key + default to `default_settings()`; show it in a panel form with `f_text()` / `f_switch()`; add the key to that page\'s save list.';
    $L[] = '- **A new table or column** → add it to `app/schema.php` (new table) or as an `ALTER TABLE` inside `nx_migrate()` under a new `if ($v < N)` block, then **increase** `NX_DB_VERSION` by one.';
    $L[] = '- **Styles** → `assets/css/style.css` (site) and `assets/css/admin.css` (panel); append new rules at the end of the file.';
    $L[] = '- **Scripts** → `assets/js/app.js` (site, inside the first IIFE where `$`, `$$`, `api()`, `toast()` exist) and `assets/js/admin.js` (panel).';
    $L[] = '';
    $L[] = '## Never touch ❌';
    $L[] = '';
    $L[] = '- `app/config.php` secrets and the `secret` value (changing it logs everyone out and breaks signed links). Never put it in Git or send it to anyone.';
    $L[] = '- **Hidden panel entrance** (`app/security.php`: `admin_gate_*`). The panel URL is a long secret link; only its **sha256 hash** is stored (`admin_gate_hash`). Never write the real link into code, README or Git. If it is lost, create `app/admin-unlock.txt` on the host to get in once, then make a new link in Panel → Security.';
    $L[] = '- Password hashing (`password_hash` / `password_verify`), CSRF checks, rate limits, captcha, session hardening and the security block of `.htaccess`.';
    $L[] = '- Minimum password length is **8** characters — do not raise it.';
    $L[] = '- `NX_DB_VERSION` must only go **up**; never edit or delete an old `if ($v < N)` migration block (live sites already ran them).';
    $L[] = '- Existing setting keys, table names and column names (the live database depends on them). Add new ones instead of renaming.';
    $L[] = '- `uploads/private/` (payment receipts, must stay non-public) and the `.htaccess` files inside `uploads/` and `app/`.';
    $L[] = '- Jalali date functions in `app/jdate.php` (all dates and the monthly report rely on them). Note: `jalali_month_names()` is indexed **1–12**.';
    $L[] = '- Do not add frameworks, Composer, npm or a build step: the site must keep working by just uploading files to cPanel.';
    $L[] = '';
    $L[] = '## Prompt to give an AI together with this ZIP';
    $L[] = '';
    $L[] = '```';
    $L[] = 'This is my website project (PHP 8 without framework, MySQL, vanilla JS/CSS, Persian RTL, Jalali dates, hosted on cPanel).';
    $L[] = 'First read nexushub/README.md completely, then FEATURES-fa.md. Follow the "Coding rules", only change things listed under';
    $L[] = '"To change it" and never touch anything under "Never touch". Keep all UI text in Persian. When you finish, list every file you';
    $L[] = 'changed, and give me the changed files ready to upload. My request: ...';
    $L[] = '```';
    $L[] = '';
    return implode("\n", $L) . "\n";
}

function project_routes(string $file): array
{
    if (!is_file($file)) return [];
    preg_match_all("~^    case '([a-z0-9_-]+)':~m", (string) file_get_contents($file), $m);
    return array_values(array_unique($m[1]));
}

/** panel menu (label, file) without permission filtering, for the README */
function adm_nav_for_doc(): array
{
    if (!function_exists('adm_nav')) return [];
    return array_map(fn($n) => [$n[0], $n[1]], adm_nav());
}
