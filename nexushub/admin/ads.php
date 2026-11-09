<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'toggle': q('UPDATE ads SET is_active = 1 - is_active WHERE id = ?', [$id]); flash('وضعیت تبلیغ تغییر کرد.'); break;
        case 'delete': q('DELETE FROM ads WHERE id = ?', [$id]); flash('تبلیغ حذف شد.'); break;
        case 'reset': q('UPDATE ads SET impressions = 0, clicks = 0 WHERE id = ?', [$id]); flash('آمار تبلیغ صفر شد.'); break;
        case 'duplicate':
            $a = row('SELECT * FROM ads WHERE id = ?', [$id]);
            if ($a) { unset($a['id']); $a['title'] .= ' (کپی)'; $a['is_active'] = 0; $a['impressions'] = 0; $a['clicks'] = 0; $a['created_at'] = now(); $nid = insert('ads', $a); flash('کپی تبلیغ ساخته شد (غیرفعال).'); redirect('admin/ad-edit.php?id=' . $nid); }
            break;
    }
    redirect('admin/ads.php' . (!empty($_GET['pos']) ? '?pos=' . urlencode((string) $_GET['pos']) : ''));
}

$positions = ad_positions();
$pos = (string) ($_GET['pos'] ?? '');
$all = rows('SELECT * FROM ads ORDER BY is_active DESC, priority DESC, id DESC');
$byPos = [];
foreach ($all as $x) foreach (ad_places_of($x) as $pp) $byPos[$pp] = ($byPos[$pp] ?? 0) + 1;
$list = $pos && isset($positions[$pos]) ? array_values(array_filter($all, fn($x) => in_array($pos, ad_places_of($x), true))) : $all;
$tot = row('SELECT COALESCE(SUM(impressions),0) i, COALESCE(SUM(clicks),0) c, SUM(is_active) a, COUNT(*) n FROM ads');

adm_head('مدیریت تبلیغات', 'ads');
?>
<div class="grid g4 stats-grid mb">
    <div class="stat"><span class="stat-ic"><?= adm_icon('ads', 24) ?></span><div><b><?= fa_num((string) (int) $tot['n']) ?></b><small>کل تبلیغات</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3ddc84,#1de9b6);--sh:rgba(61,220,132,.7);--c:rgba(61,220,132,.3)"><span class="stat-ic"><?= icon('check', 24) ?></span><div><b><?= fa_num((string) (int) $tot['a']) ?></b><small>فعال</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3dbbff,#7c4dff);--sh:rgba(61,187,255,.7);--c:rgba(61,187,255,.3)"><span class="stat-ic"><?= icon('eye', 24) ?></span><div><b><?= fa_short_num((int) $tot['i']) ?></b><small>نمایش</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#ffc53d,#ff7a1f);--sh:rgba(255,197,61,.7);--c:rgba(255,197,61,.3)"><span class="stat-ic"><?= adm_icon('click', 24) ?></span><div><b><?= fa_short_num((int) $tot['c']) ?></b><small>کلیک · CTR <?= fa_num($tot['i'] ? number_format($tot['c'] / $tot['i'] * 100, 1) : '0') ?>٪</small></div></div>
</div>

<div class="card">
    <div class="card-head">
        <div class="tabs" style="margin:0">
            <a href="ads.php" class="<?= $pos === '' ? 'active' : '' ?>">همه</a>
            <?php foreach ($positions as $k => $p): ?><a href="?pos=<?= $k ?>" class="<?= $pos === $k ? 'active' : '' ?>"><?= e($p['name']) ?><em>(<?= fa_num((string) ($byPos[$k] ?? 0)) ?>)</em></a><?php endforeach; ?>
        </div>
        <a class="abtn abtn-primary" href="ad-edit.php<?= $pos ? '?pos=' . e($pos) : '' ?>"><?= adm_icon('plus', 18) ?> تبلیغ جدید</a>
    </div>
    <?php if (!$list): ?><div class="empty-state"><span>📢</span>تبلیغی در این بخش وجود ندارد.</div><?php endif; ?>
    <div class="nxp-cards">
        <?php foreach ($list as $a):
            $live = $a['is_active'] && (!$a['start_at'] || $a['start_at'] <= now()) && (!$a['end_at'] || $a['end_at'] >= now());
            $ctr = $a['impressions'] ? $a['clicks'] / $a['impressions'] * 100 : 0; ?>
            <div class="nxp-card" style="<?= $a['is_active'] ? '' : 'opacity:.6' ?>">
                <div class="nxp-prev"><?= render_ad($a, true) ?></div>
                <div class="nxp-meta">
                    <div class="row-flex" style="justify-content:space-between">
                        <h4><?= e($a['title']) ?></h4>
                        <?= $live ? '<span class="badge b-ok">● در حال نمایش</span>' : ($a['is_active'] ? '<span class="badge b-warn">خارج از بازه زمانی</span>' : '<span class="badge b-muted">غیرفعال</span>') ?>
                    </div>
                    <div class="row-flex" style="gap:6px">
                        <?php foreach (ad_places_of($a) as $pp): ?><span class="badge">📍 <?= e($positions[$pp]['name'] ?? $pp) ?></span><?php endforeach; ?>
                        <span class="badge b-info"><?= e(ad_themes()[$a['theme']]['name'] ?? $a['theme']) ?></span>
                        <?php if ($a['device'] !== 'all'): ?><span class="badge"><?= $a['device'] === 'mobile' ? '📱 موبایل' : '🖥 دسکتاپ' ?></span><?php endif; ?>
                    </div>
                    <div class="nxp-stats">
                        <span>نمایش: <b><?= fa_num(number_format((int) $a['impressions'])) ?></b></span>
                        <span>کلیک: <b><?= fa_num(number_format((int) $a['clicks'])) ?></b></span>
                        <span>CTR: <b class="ctr"><?= fa_num(number_format($ctr, 2)) ?>٪</b></span>
                    </div>
                    <?php if ($a['start_at'] || $a['end_at']): ?>
                        <div class="small muted"><?= icon('calendar', 13) ?> <?= $a['start_at'] ? jdate('j F H:i', $a['start_at']) : 'از الان' ?> ← <?= $a['end_at'] ? jdate('j F Y H:i', $a['end_at']) : 'نامحدود' ?></div>
                    <?php endif; ?>
                    <form method="post" class="nxp-actions">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <a class="abtn abtn-primary abtn-xs" href="ad-edit.php?id=<?= (int) $a['id'] ?>"><?= adm_icon('edit', 14) ?> ویرایش</a>
                        <button class="abtn <?= $a['is_active'] ? 'abtn-ghost' : 'abtn-ok' ?> abtn-xs" name="act" value="toggle"><?= $a['is_active'] ? 'غیرفعال' : 'فعال' ?> کن</button>
                        <button class="abtn abtn-ghost abtn-xs" name="act" value="duplicate">کپی</button>
                        <button class="abtn abtn-ghost abtn-xs" name="act" value="reset" data-confirm="آمار این تبلیغ صفر شود؟">صفر کردن آمار</button>
                        <button class="abtn abtn-danger abtn-xs" name="act" value="delete" data-confirm="این تبلیغ حذف شود؟">حذف</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php adm_foot();
