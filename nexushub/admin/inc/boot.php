<?php
/**
 * هسته‌ی پنل مدیریت: لایه‌بندی و توابع فرم
 */
require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

if (!defined('NX_ADMIN_PUBLIC')) {
    $ME = require_login();
}

function adm_icon(string $name, int $size = 20): string
{
    static $ext = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'posts' => '<path d="M4 4h12l4 4v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/><path d="M8 10h8M8 14h8M8 18h5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'percent' => '<path d="M19 5 5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'wheel' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2"/><path d="M12 3v7M12 14v7M3 12h7M14 12h7M5.6 5.6l5 5M13.4 13.4l5 5M18.4 5.6l-5 5M10.6 13.4l-5 5"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'ads' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M16 8a5 5 0 0 1 0 8M19 5a9 9 0 0 1 0 14"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1 0 1.6-.8 1.6-1.6 0-.5-.2-.8-.5-1.1-.3-.3-.5-.7-.5-1.1 0-.9.7-1.6 1.6-1.6H16a5 5 0 0 0 5-5c0-4-4-7.6-9-7.6Z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10" cy="7" r="1.2"/><circle cx="15" cy="7.5" r="1.2"/>',
        'pages' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-5-6.7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash' => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1Z"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9Z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'story' => '<circle cx="12" cy="12" r="9" stroke-dasharray="4 2.2"/><circle cx="12" cy="12" r="5"/>',
        'click' => '<path d="m9 9 5 12 1.8-5.2L21 14Z"/><path d="M7.2 2.2 8 5.1M5.1 8l-2.9-.8M14 4.1 12 6M6 12l-1.9 2"/>',
        'bag' => '<path d="M5 7h14l-1.2 12.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'orders' => '<path d="M5 2h14v20l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'ticket' => '<path d="M3 12a9 9 0 1 1 18 0v5a2 2 0 0 1-2 2h-2v-6h4M3 12v5a2 2 0 0 0 2 2h2v-6H3"/>',
        'shopset' => '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8"/>',
    ];
    if (isset($ext[$name])) {
        return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ext[$name] . '</svg>';
    }
    return icon($name, $size);
}

function adm_nav(): array
{
    $pendingC = (int) val("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    $unread = (int) val('SELECT COUNT(*) FROM messages WHERE is_read = 0');
    $newMembers = (int) val('SELECT COUNT(*) FROM members WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
    shop_expire_due();
    $toReview = (int) val("SELECT COUNT(*) FROM orders WHERE status IN ('reviewing','approved')");
    $openT = (int) val("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
    try { $raffleReq = (int) val("SELECT COUNT(*) FROM raffle_requests WHERE status = 'pending'"); } catch (Throwable $e) { $raffleReq = 0; }
    $wheelTodo = 0;
    try { prizes_expire_due(); $prizeTodo = (int) val("SELECT COUNT(*) FROM prizes WHERE status = 'pending'"); } catch (Throwable $e) { $prizeTodo = 0; }
    return [
        ['داشبورد', 'index.php', 'dashboard', null, 'dashboard', 0],
        ['سفارش‌ها', 'orders.php', 'orders', 'shop', 'orders', $toReview],
        ['محصولات فروشگاه', 'products.php', 'bag', 'shop', 'products', 0],
        ['تیکت‌های پشتیبانی', 'tickets.php', 'ticket', 'shop', 'tickets', $openT],
        ['تنظیمات فروشگاه', 'shop-settings.php', 'shopset', '*', 'shop-settings', 0],
        ['همه اخبار', 'posts.php', 'posts', 'posts', 'posts', 0],
        ['افزودن خبر', 'post-edit.php', 'plus', 'posts', 'post-new', 0],
        ['دسته‌بندی‌ها', 'categories.php', 'folder', 'categories', 'categories', 0],
        ['نظرات', 'comments.php', 'comment', 'comments', 'comments', $pendingC],
        ['کتابخانه رسانه', 'media.php', 'image', 'media', 'media', 0],
        ['استوری‌ها', 'stories.php', 'story', '*', 'stories', 0],
        ['تبلیغات', 'ads.php', 'ads', '*', 'ads', 0],
        ['قرعه‌کشی', 'raffles.php', 'gift', '*', 'raffles', $raffleReq],
        ['گردونه شانس', 'wheels.php', 'wheel', '*', 'wheels', $wheelTodo],
        ['جایزه‌ها و برنده‌ها', 'prizes.php', 'gift', '*', 'prizes', $prizeTodo],
        ['کدهای تخفیف', 'promos.php', 'percent', '*', 'promos', 0],
        ['ظاهر سایت', 'appearance.php', 'palette', '*', 'appearance', 0],
        ['صفحات', 'pages.php', 'pages', 'pages', 'pages', 0],
        ['پیام‌ها', 'messages.php', 'inbox', 'messages', 'messages', $unread],
        ['اعضای سایت', 'members.php', 'user', '*', 'members', $newMembers],
        ['کاربران پنل', 'users.php', 'users', '*', 'users', 0],
        ['تنظیمات', 'settings.php', 'settings', '*', 'settings', 0],
        ['پشتیبان‌گیری', 'backup.php', 'database', '*', 'backup', 0],
    ];
}

function adm_head(string $title, string $active = ''): void
{
    $u = current_user();
    $colors = theme_colors();
    ?><!doctype html>
<html lang="fa" dir="rtl" class="<?= theme_colors()['light'] ? 'theme-light' : 'theme-dark' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | پنل مدیریت <?= e(setting('site_title')) ?></title>
<link rel="icon" href="<?= e(media_url((string) setting('favicon'))) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/boxes.css')) ?>">
<?php $decorHtml = decor_html('admin'); if ($decorHtml): ?><link rel="stylesheet" href="<?= e(asset('css/decor.css')) ?>"><?php endif; ?>
<style><?= theme_css_vars() ?></style>
<?= perf_head() ?>
</head>
<body class="adm-body cards-<?= e(isset(card_styles()[(string) setting('card_style', 'glass')]) ? (string) setting('card_style', 'glass') : 'glass') ?>">
<div class="adm-bg" aria-hidden="true"><i></i><i></i></div>
<?= $decorHtml ?>
<div class="adm">
    <aside class="adm-side" id="admSide">
        <a class="adm-brand" href="<?= e(url('admin/')) ?>">
            <span class="adm-logo"><img src="<?= e(logo_url()) ?>" alt=""></span>
            <span><b><?= e(setting('site_title')) ?></b><small>پنل مدیریت</small></span>
        </a>
        <nav class="adm-nav">
            <?php foreach (adm_nav() as [$label, $href, $ic, $cap, $key, $badge]):
                if ($cap === '*' && !can('*')) continue;
                if ($cap && $cap !== '*' && !can($cap)) continue; ?>
                <a href="<?= e(url('admin/' . $href)) ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <?= adm_icon($ic, 19) ?><span><?= e($label) ?></span>
                    <?php if ($badge): ?><em class="nav-badge"><?= fa_num((string) $badge) ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="adm-user">
            <?= user_avatar($u, 40) ?>
            <div><b><?= e($u['display_name'] ?: $u['username']) ?></b><small><?= e(role_names()[$u['role']] ?? $u['role']) ?></small></div>
            <a class="adm-ib" href="<?= e(url('admin/profile.php')) ?>" title="پروفایل"><?= adm_icon('edit', 17) ?></a>
            <a class="adm-ib" href="<?= e(url('admin/logout.php')) ?>" title="خروج"><?= adm_icon('logout', 17) ?></a>
        </div>
    </aside>
    <div class="adm-overlay" id="admOverlay"></div>
    <div class="adm-main">
        <header class="adm-top">
            <button class="adm-ib adm-burger" id="admBurger" aria-label="منو"><?= icon('menu', 22) ?></button>
            <h1 class="adm-title"><?= e($title) ?></h1>
            <span class="adm-date"><?= icon('calendar', 15) ?> <?= jdate('l j F Y') ?></span>
            <div class="adm-top-actions">
                <?php if (can('shop')): $an = notifs_unread('admin'); ?>
                <div class="anb">
                    <button class="adm-ib anb-btn" id="anbBtn" type="button" aria-label="اعلان‌ها" title="اعلان‌ها"><?= icon('bell', 19) ?><em class="nav-badge anb-count" id="anbCount"<?= $an ? '' : ' hidden' ?>><?= fa_num((string) $an) ?></em></button>
                    <div class="anb-panel" id="anbPanel" hidden><div class="anb-head"><b>🔔 اعلان‌های فروشگاه</b><a href="<?= e(url('admin/orders.php')) ?>">همه سفارش‌ها</a></div><div class="anb-list" id="anbList"></div></div>
                </div>
                <?php endif; ?>
                <?php if (can('posts')): ?><a class="abtn abtn-primary abtn-sm" href="<?= e(url('admin/post-edit.php')) ?>"><?= adm_icon('plus', 17) ?><span>خبر جدید</span></a><?php endif; ?>
                <a class="abtn abtn-ghost abtn-sm" href="<?= e(url('')) ?>" target="_blank"><?= icon('external', 16) ?><span>مشاهده سایت</span></a>
            </div>
        </header>
        <main class="adm-content">
    <?php
}

function adm_foot(array $scripts = []): void
{
    $fl = flashes();
    ?>
        </main>
        <footer class="adm-foot">NEXUS | HUB CMS · نسخه <?= fa_num(NX_VERSION) ?> · <?= jdate('Y') ?></footer>
    </div>
</div>
<div class="toasts" id="toasts"></div>
<div class="modal" id="mediaModal" hidden>
    <div class="modal-backdrop" data-close></div>
    <div class="modal-box">
        <div class="modal-head"><b>انتخاب تصویر</b>
            <label class="abtn abtn-primary abtn-sm"><?= adm_icon('upload', 16) ?> آپلود جدید<input type="file" accept="image/*" multiple hidden id="mmUpload"></label>
            <button class="adm-ib" data-close aria-label="بستن"><?= icon('close', 18) ?></button>
        </div>
        <div class="mm-grid" id="mmGrid"></div>
        <div class="mm-foot"><button class="abtn abtn-ghost abtn-sm" id="mmMore" hidden>بارگذاری بیشتر</button></div>
    </div>
</div>
<script>
window.NXA = <?= json_encode([
    'base' => nx_base_path(),
    'csrf' => csrf_token(),
    'ajax' => url('admin/ajax.php'),
    'flashes' => $fl,
    'uploadLimit' => server_upload_limit(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('js/confirm.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<script src="<?= e(asset('js/pw.js')) ?>" defer></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset('js/' . $s)) ?>"></script><?php endforeach; ?>
<?= perf_foot() ?>
</body>
</html>
<?php
}

/* ---------------- فرم‌ها ---------------- */

function f_text(string $name, string $label, $value = '', array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $attrs = '';
    foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'id'] as $a) if (isset($o[$a])) $attrs .= ' ' . $a . '="' . e($o[$a]) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    if (!empty($o['ltr'])) $attrs .= ' dir="ltr"';
    if (!empty($o['attrs'])) $attrs .= ' ' . $o['attrs'];
    return '<label class="af"><span class="af-label">' . e($label) . '</span><input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>'
        . (!empty($o['hint']) ? '<small class="af-hint">' . $o['hint'] . '</small>' : '') . '</label>';
}

function f_textarea(string $name, string $label, $value = '', array $o = []): string
{
    $rows = (int) ($o['rows'] ?? 4);
    $attrs = !empty($o['ltr']) ? ' dir="ltr"' : '';
    if (isset($o['placeholder'])) $attrs .= ' placeholder="' . e($o['placeholder']) . '"';
    if (isset($o['maxlength'])) $attrs .= ' maxlength="' . (int) $o['maxlength'] . '"';
    if (!empty($o['attrs'])) $attrs .= ' ' . $o['attrs'];
    return '<label class="af"><span class="af-label">' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '"' . $attrs . (!empty($o['code']) ? ' class="code"' : '') . '>' . e($value) . '</textarea>'
        . (!empty($o['hint']) ? '<small class="af-hint">' . $o['hint'] . '</small>' : '') . '</label>';
}

function f_select(string $name, string $label, array $options, $value = '', array $o = []): string
{
    $h = '<label class="af"><span class="af-label">' . e($label) . '</span><select name="' . e($name) . '"' . (!empty($o['attrs']) ? ' ' . $o['attrs'] : '') . '>';
    foreach ($options as $k => $v) $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h . '</select>' . (!empty($o['hint']) ? '<small class="af-hint">' . $o['hint'] . '</small>' : '') . '</label>';
}

function f_switch(string $name, string $label, bool $checked, string $hint = ''): string
{
    return '<label class="sw-row"><span class="sw-text"><b>' . e($label) . '</b>' . ($hint ? '<small>' . e($hint) . '</small>' : '') . '</span>'
        . '<input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" class="sw" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '></label>';
}

function f_color(string $name, string $label, string $value): string
{
    return '<label class="af af-color"><span class="af-label">' . e($label) . '</span><span class="color-field"><input type="color" value="' . e($value) . '" data-sync="' . e($name) . '"><input type="text" name="' . e($name) . '" value="' . e($value) . '" dir="ltr" maxlength="7" pattern="#[0-9a-fA-F]{6}"></span></label>';
}

function f_range(string $name, string $label, $value, int $min, int $max, string $unit = ''): string
{
    return '<label class="af af-range"><span class="af-label">' . e($label) . ' <output>' . e($value) . '</output>' . e($unit) . '</span><input type="range" name="' . e($name) . '" value="' . e($value) . '" min="' . $min . '" max="' . $max . '"></label>';
}

function f_image(string $name, string $label, ?string $value, string $hint = '', array $presets = []): string
{
    $url = $value ? media_url($value) : '';
    $pre = '';
    if ($presets) {
        $pre = '<div class="img-presets"><small class="af-label">🖼️ تصاویر آماده</small><div class="ip-grid">';
        foreach ($presets as $path => $title) $pre .= '<button type="button" class="ip" data-preset="' . e($path) . '" data-url="' . e(media_url($path)) . '" title="' . e($title) . '"><img src="' . e(media_url($path)) . '" alt="' . e($title) . '" loading="lazy"></button>';
        $pre .= '</div></div>';
    }
    return '<div class="af af-image" data-image-field><span class="af-label">' . e($label) . '</span>'
        . '<div class="img-pick' . ($url ? ' has' : '') . '"><img src="' . e($url) . '" alt=""><span class="img-empty">' . adm_icon('image', 30) . '<small>انتخاب یا آپلود تصویر</small></span></div>'
        . '<input type="hidden" name="' . e($name) . '" value="' . e((string) $value) . '">'
        . '<div class="img-actions"><button type="button" class="abtn abtn-ghost abtn-sm" data-pick>' . adm_icon('image', 15) . ' انتخاب</button><button type="button" class="abtn abtn-ghost abtn-sm" data-clear>' . adm_icon('trash', 15) . ' حذف</button></div>'
        . ($hint ? '<small class="af-hint">' . e($hint) . '</small>' : '') . $pre . '</div>';
}

/** Ready-made images that ship with the site (assets/img/presets) */
function preset_images(string $set = 'product'): array
{
    if ($set === 'cover') return [
        'assets/img/covers/gaming-news.svg' => 'Gaming News', 'assets/img/covers/breaking.svg' => 'Breaking (فوری)', 'assets/img/covers/esports.svg' => 'Esports',
        'assets/img/covers/trailer.svg' => 'تریلر جدید', 'assets/img/covers/update.svg' => 'آپدیت / پچ', 'assets/img/covers/review.svg' => 'نقد و بررسی',
        'assets/img/covers/tech.svg' => 'تکنولوژی', 'assets/img/covers/rumor.svg' => 'شایعه / لیک', 'assets/img/covers/live-event.svg' => 'رویداد زنده',
        'assets/img/covers/free-games.svg' => 'بازی رایگان',
    ];
    if ($set === 'raffle') return [
        'assets/img/presets/raffle-wheel.svg' => 'گردونه‌ی شانس', 'assets/img/presets/raffle-gift.svg' => 'جعبه‌ی جایزه', 'assets/img/presets/raffle-trophy.svg' => 'جام قهرمانی',
        'assets/img/presets/raffle-tickets.svg' => 'تیکت قرعه‌کشی', 'assets/img/presets/raffle-jackpot.svg' => 'جک‌پات ۷۷۷', 'assets/img/presets/raffle-stars.svg' => 'ستاره‌ها',
        'assets/img/presets/premium.svg' => 'پریمیوم تلگرام', 'assets/img/presets/stars.svg' => 'استارز', 'assets/img/presets/ton.svg' => 'تون',
        'assets/img/presets/gift.svg' => 'گیفت کارت', 'assets/img/presets/game.svg' => 'بازی', 'assets/img/presets/telegram.svg' => 'تلگرام',
    ];
    return [
        'assets/img/presets/premium.svg' => 'پریمیوم تلگرام', 'assets/img/presets/stars.svg' => 'استارز', 'assets/img/presets/ton.svg' => 'تون / کریپتو',
        'assets/img/presets/gift.svg' => 'گیفت کارت', 'assets/img/presets/game.svg' => 'بازی', 'assets/img/presets/account.svg' => 'اکانت / اشتراک',
        'assets/img/presets/service.svg' => 'خدمات', 'assets/img/presets/telegram.svg' => 'تلگرام', 'assets/img/presets/sale.svg' => 'تخفیف / فروش ویژه',
        'assets/img/presets/shop.svg' => 'فروشگاه',
    ];
}

/** "prize delivery" settings (shared by raffles and the lucky wheel) */
function prize_claim_save(): bool
{
    if (($_POST['act'] ?? '') !== 'claim') return false;
    $ids = implode("\n", array_keys(prize_claim_ids_from((string) ($_POST['prize_claim_ids'] ?? ''))));
    save_settings(['prize_claim_on' => !empty($_POST['prize_claim_on']) ? '1' : '0', 'prize_claim_hours' => (string) max(1, min(720, (int) en_num((string) ($_POST['prize_claim_hours'] ?? 24)))), 'prize_claim_ids' => $ids]);
    flash('تنظیمات تحویل جایزه ذخیره شد ✅');
    return true;
}
function prize_claim_ids_from(string $raw): array
{
    $out = [];
    foreach (preg_split('~[\r\n,،]+~u', $raw) as $id) {
        $id = trim(preg_replace('~^(https?://)?(t\.me|telegram\.me)/~i', '', $id), " @/\t");
        if ($id !== '' && preg_match('~^[A-Za-z0-9_]{3,40}$~', $id)) $out['@' . $id] = 1;
    }
    return $out;
}
function prize_claim_card(): string
{
    $hours = prize_claim_hours();
    $chips = '';
    foreach ([6 => '۶ ساعت', 12 => '۱۲ ساعت', 24 => '۲۴ ساعت', 48 => '۲ روز', 72 => '۳ روز', 168 => '۱ هفته'] as $v => $t) $chips .= '<button type="button" data-v="' . $v . '"' . ($v === $hours ? ' class="on"' : '') . '>' . $t . '</button>';
    return '<form method="post" class="card pc-card" id="pcForm">' . csrf_field() . '<input type="hidden" name="act" value="claim">'
        . '<div class="card-head"><h3>📩 تحویل جایزه به برنده‌ها</h3><small class="muted">برای قرعه‌کشی و گردونه شانس</small></div>'
        . '<div class="pc-grid"><div>'
        . f_switch('prize_claim_on', 'به برنده پیام بده که برای دریافت جایزه بهت پیام بده', prize_claim_on(), 'توی اعلان برنده، صفحه‌ی قرعه‌کشی/گردونه و کارت برنده نشون داده می‌شه')
        . f_text('prize_claim_hours', 'مهلت پیام دادن (ساعت بعد از برنده شدن)', (string) $hours, ['type' => 'number', 'min' => 1, 'max' => 720, 'hint' => 'بعد از این مدت جایزه باطل می‌شه'])
        . '<div class="rf-quick" data-for="prize_claim_hours">' . $chips . '</div></div><div>'
        . '<label class="af"><span class="af-label">آیدی‌های تلگرام (هر خط یکی — هر چند تا خواستی)</span><textarea name="prize_claim_ids" rows="4" dir="ltr" placeholder="@NexusHubIR&#10;@NexusSupport">' . e((string) setting('prize_claim_ids', '')) . '</textarea><small class="af-hint">با @ یا بدون @، یا لینک t.me — اگه خالی باشه، برنده به صفحه‌ی پشتیبانی فرستاده می‌شه.</small></label>'
        . '<div class="pc-prev"><small>پیامی که برنده می‌گیره:</small><p id="pcPrev">' . e(prize_claim_text()) . '</p></div>'
        . '</div></div>'
        . '<button class="rf-save pc-save" type="submit"><span class="rf-save-check">' . icon('check', 18) . '</span><span>ذخیره‌ی تنظیمات تحویل جایزه</span></button>'
        . '</form>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){var f=document.getElementById("pcForm");if(!f)return;var h=f.querySelector("[name=prize_claim_hours]"),q=f.querySelector(".rf-quick");'
        . 'var s=function(){[].forEach.call(q.querySelectorAll("button"),function(b){b.classList.toggle("on",b.dataset.v===String(parseInt(h.value,10)));});};'
        . 'q.addEventListener("click",function(e){var b=e.target.closest("[data-v]");if(b){h.value=b.dataset.v;s();}});h.addEventListener("input",s);});</script>';
}

function f_jdate(string $name, string $label, ?string $datetime, string $hint = ''): string
{
    return '<label class="af"><span class="af-label">' . e($label) . '</span><span class="jd-field"><input type="text" name="' . e($name) . '" value="' . e(datetime_to_jalali_input($datetime)) . '" dir="ltr" placeholder="1405/08/12 14:30" data-jdp autocomplete="off">' . icon('calendar', 18) . '</span>'
        . ($hint ? '<small class="af-hint">' . e($hint) . '</small>' : '') . '</label>';
}

function status_badge(string $status, ?string $publishedAt = null): string
{
    if ($status === 'published' && $publishedAt && strtotime($publishedAt) > time()) return '<span class="badge b-info">زمان‌بندی‌شده</span>';
    return match ($status) {
        'published' => '<span class="badge b-ok">منتشر شده</span>',
        'draft' => '<span class="badge b-muted">پیش‌نویس</span>',
        'pending' => '<span class="badge b-warn">در انتظار</span>',
        'approved' => '<span class="badge b-ok">تایید شده</span>',
        'spam' => '<span class="badge b-err">اسپم</span>',
        default => '<span class="badge">' . e($status) . '</span>',
    };
}

function post_check(): void
{
    if (!is_post()) return;
    csrf_check();
}

function order_badge(string $st): string
{
    $S = order_statuses();
    $cls = ['pending_payment' => 'b-warn', 'reviewing' => 'b-info', 'rejected' => 'b-err', 'approved' => 'b-hot', 'completed' => 'b-ok', 'canceled' => 'b-muted', 'expired' => 'b-muted'][$st] ?? 'b-muted';
    return '<span class="badge ' . $cls . '">' . e(($S[$st]['icon'] ?? '') . ' ' . ($S[$st]['name'] ?? $st)) . '</span>';
}

function ticket_badge(string $st): string
{
    $T = ticket_statuses();
    return '<span class="badge ' . (['open' => 'b-warn', 'answered' => 'b-ok', 'closed' => 'b-muted'][$st] ?? 'b-muted') . '">' . e($T[$st]['name'] ?? $st) . '</span>';
}
