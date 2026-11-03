<?php
require __DIR__ . '/inc/boot.php';

$today = date('Y-m-d');
$st = row('SELECT * FROM stats_daily WHERE day = ?', [$today]) ?: ['views' => 0, 'visitors' => 0, 'post_views' => 0, 'ad_clicks' => 0];
$totPosts = (int) val("SELECT COUNT(*) FROM posts WHERE status = 'published'");
$drafts = (int) val("SELECT COUNT(*) FROM posts WHERE status = 'draft'");
$scheduled = (int) val("SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at > ?", [now()]);
$totViews = (int) val('SELECT COALESCE(SUM(views),0) FROM posts');
$pendingC = (int) val("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
$adsActive = (int) val('SELECT COUNT(*) FROM ads WHERE is_active = 1');
$adClicks = (int) val('SELECT COALESCE(SUM(clicks),0) FROM ads');
$adImps = (int) val('SELECT COALESCE(SUM(impressions),0) FROM ads');

// نمودار ۳۰ روز
$days = [];
for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = ['views' => 0, 'visitors' => 0];
foreach (rows('SELECT * FROM stats_daily WHERE day >= ?', [array_key_first($days)]) as $r) {
    if (isset($days[$r['day']])) $days[$r['day']] = ['views' => (int) $r['views'], 'visitors' => (int) $r['visitors']];
}
$chart = [
    'labels' => array_map(fn($d) => jdate('j F', $d), array_keys($days)),
    'series' => [
        ['name' => 'بازدید', 'color' => theme_colors()['primary'], 'values' => array_column(array_values($days), 'views')],
        ['name' => 'بازدیدکننده', 'color' => '#3dbbff', 'values' => array_column(array_values($days), 'visitors')],
    ],
];
$sum30 = array_sum(array_column(array_values($days), 'views'));

$top = rows("SELECT id, title, slug, views FROM posts WHERE status = 'published' ORDER BY views DESC LIMIT 6");
$recent = rows(POST_SELECT . ' ORDER BY p.created_at DESC LIMIT 6');
$comments = rows('SELECT c.*, p.title AS post_title FROM comments c LEFT JOIN posts p ON p.id = c.post_id ORDER BY c.created_at DESC LIMIT 5');
$catStats = rows("SELECT c.name, c.icon, c.color, COUNT(p.id) AS cnt, COALESCE(SUM(p.views),0) AS v FROM categories c LEFT JOIN posts p ON p.category_id = c.id AND p.status='published' GROUP BY c.id ORDER BY v DESC");
$maxCat = max(1, ...array_map(fn($c) => (int) $c['v'], $catStats ?: [['v' => 1]]));

adm_head('داشبورد', 'dashboard');
$u = current_user();
?>
<div class="card" style="margin-bottom:18px;overflow:hidden">
    <div class="row-flex" style="justify-content:space-between">
        <div>
            <h2 style="margin:0;font-size:1.25rem">سلام <?= e($u['display_name'] ?: $u['username']) ?> 👋</h2>
            <p class="muted" style="margin:4px 0 0">امروز <?= jdate('l j F Y') ?> است. <?= $pendingC ? '<a href="comments.php?status=pending" style="color:var(--warn)">' . fa_num((string) $pendingC) . ' نظر در انتظار تایید داری.</a>' : 'همه چیز مرتبه ✨' ?></p>
        </div>
        <div class="row-flex">
            <a class="abtn abtn-primary" href="post-edit.php"><?= adm_icon('plus', 18) ?> انتشار خبر جدید</a>
            <?php if (can('*')): ?><a class="abtn abtn-ghost" href="ad-edit.php"><?= adm_icon('ads', 18) ?> تبلیغ جدید</a><?php endif; ?>
        </div>
    </div>
</div>

<div class="grid g4 stats-grid">
    <div class="stat"><span class="stat-ic"><?= icon('eye', 24) ?></span><div><b data-count="<?= (int) $st['views'] ?>"><?= fa_num(number_format((int) $st['views'])) ?></b><small>بازدید امروز</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3dbbff,#7c4dff);--sh:rgba(61,187,255,.7);--c:rgba(61,187,255,.3)"><span class="stat-ic"><?= icon('user', 24) ?></span><div><b><?= fa_num(number_format((int) $st['visitors'])) ?></b><small>بازدیدکننده امروز</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3ddc84,#1de9b6);--sh:rgba(61,220,132,.7);--c:rgba(61,220,132,.3)"><span class="stat-ic"><?= adm_icon('posts', 24) ?></span><div><b><?= fa_num((string) $totPosts) ?></b><small>خبر منتشر شده</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#ffc53d,#ff7a1f);--sh:rgba(255,197,61,.7);--c:rgba(255,197,61,.3)"><span class="stat-ic"><?= adm_icon('click', 24) ?></span><div><b><?= fa_num(number_format($adClicks)) ?></b><small>کلیک تبلیغات</small></div></div>
</div>

<div class="split mt">
    <div>
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('chart', 20) ?> آمار بازدید ۳۰ روز اخیر</h3><span class="badge"><?= fa_num(number_format($sum30)) ?> بازدید</span></div>
            <div class="chart-wrap" data-chart='<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>'></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('posts', 20) ?> آخرین اخبار</h3><a class="abtn abtn-ghost abtn-sm" href="posts.php">همه</a></div>
            <div class="tbl-wrap"><table class="tbl"><tbody>
                <?php foreach ($recent as $p): ?>
                    <tr>
                        <td style="width:70px"><?= $p['cover'] ? '<img class="thumb" src="' . e(media_url($p['cover'])) . '" alt="">' : '<span class="thumb" style="display:grid;place-items:center">' . e($p['cat_icon'] ?? '📰') . '</span>' ?></td>
                        <td><a class="t-title" href="post-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a><small class="muted"><?= e($p['cat_name'] ?? 'بدون دسته') ?> · <?= jago($p['created_at']) ?></small></td>
                        <td class="hide-m"><?= status_badge($p['status'], $p['published_at']) ?></td>
                        <td class="hide-m muted small"><?= icon('eye', 14) ?> <?= fa_short_num((int) $p['views']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="split-side">
        <div class="card">
            <div class="card-head"><h3><?= icon('fire', 20) ?> پربازدیدترین‌ها</h3></div>
            <?php foreach ($top as $i => $p): ?>
                <div class="list-item"><span class="rank"><?= fa_num((string) ($i + 1)) ?></span><div class="li-body"><a href="<?= e(post_url($p)) ?>" target="_blank"><b><?= e($p['title']) ?></b></a><p><?= fa_num(number_format((int) $p['views'])) ?> بازدید</p></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('folder', 20) ?> بازدید دسته‌ها</h3></div>
            <?php foreach ($catStats as $c): ?>
                <div style="margin-bottom:12px">
                    <div class="row-flex" style="justify-content:space-between;font-size:.85rem"><span><?= e($c['icon']) ?> <?= e($c['name']) ?> <small class="muted">(<?= fa_num((string) $c['cnt']) ?> خبر)</small></span><b><?= fa_short_num((int) $c['v']) ?></b></div>
                    <div class="progress"><i style="width:<?= round((int) $c['v'] / $maxCat * 100) ?>%;background:<?= e($c['color']) ?>;box-shadow:0 0 10px <?= e($c['color']) ?>"></i></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('sparkle', 20) ?> خلاصه</h3></div>
            <div class="list-item"><div class="li-body"><b>پیش‌نویس‌ها</b></div><span class="badge"><?= fa_num((string) $drafts) ?></span></div>
            <div class="list-item"><div class="li-body"><b>زمان‌بندی‌شده</b></div><span class="badge b-info"><?= fa_num((string) $scheduled) ?></span></div>
            <div class="list-item"><div class="li-body"><b>کل بازدید اخبار</b></div><span class="badge b-ok"><?= fa_num(number_format($totViews)) ?></span></div>
            <div class="list-item"><div class="li-body"><b>تبلیغات فعال</b></div><span class="badge b-hot"><?= fa_num((string) $adsActive) ?></span></div>
            <div class="list-item"><div class="li-body"><b>CTR تبلیغات</b></div><span class="ctr"><?= fa_num($adImps ? number_format($adClicks / $adImps * 100, 2) : '0') ?>٪</span></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('comment', 20) ?> آخرین نظرات</h3><a class="abtn abtn-ghost abtn-sm" href="comments.php">همه</a></div>
            <?php if (!$comments): ?><p class="muted small">نظری ثبت نشده.</p><?php endif; ?>
            <?php foreach ($comments as $c): ?>
                <div class="list-item"><span class="avatar avatar-letter" style="width:34px;height:34px"><?= e(mb_substr($c['name'], 0, 1)) ?></span><div class="li-body"><b><?= e($c['name']) ?> <?= status_badge($c['status']) ?></b><p><?= e($c['body']) ?></p><small class="muted"><?= e(mb_strimwidth((string) $c['post_title'], 0, 40, '…')) ?></small></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php adm_foot();
