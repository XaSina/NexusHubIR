<?php
/**
 * مدیریت استوری‌ها
 */
require __DIR__ . '/inc/boot.php';
require_cap('*');

function story_remaining(?string $exp): string
{
    if (!$exp) return 'همیشگی (هایلایت)';
    $d = strtotime($exp) - time();
    if ($d <= 0) return 'منقضی شده';
    if ($d < 3600) return fa_num((string) max(1, intdiv($d, 60))) . ' دقیقه مانده';
    if ($d < 86400) return fa_num((string) intdiv($d, 3600)) . ' ساعت مانده';
    return fa_num((string) intdiv($d, 86400)) . ' روز مانده';
}

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'toggle': q('UPDATE stories SET is_active = 1 - is_active WHERE id = ?', [$id]); flash('وضعیت استوری تغییر کرد.'); break;
        case 'delete': q('DELETE FROM story_items WHERE story_id = ?', [$id]); q('DELETE FROM stories WHERE id = ?', [$id]); flash('استوری حذف شد.'); break;
        case 'renew': q('UPDATE stories SET expires_at = ?, is_active = 1, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() + 86400), now(), $id]); flash('استوری برای ۲۴ ساعت دیگر تمدید شد.'); break;
    }
    redirect('admin/stories.php');
}

$list = rows('SELECT s.*, (SELECT COUNT(*) FROM story_items i WHERE i.story_id = s.id) AS n,
    (SELECT media FROM story_items i WHERE i.story_id = s.id ORDER BY sort_order, id LIMIT 1) AS first_media,
    (SELECT media_type FROM story_items i WHERE i.story_id = s.id ORDER BY sort_order, id LIMIT 1) AS first_type
    FROM stories s ORDER BY s.sort_order, s.id DESC');
$live = count(array_filter($list, fn($s) => $s['is_active'] && $s['n'] && (!$s['expires_at'] || $s['expires_at'] > now())));

adm_head('استوری‌ها', 'stories');
?>
<div class="card">
    <div class="card-head">
        <h3><?= adm_icon('story', 20) ?> استوری‌های سایت <small class="muted">(<?= fa_num((string) $live) ?> استوری در حال نمایش)</small></h3>
        <a class="abtn abtn-primary" href="story-edit.php"><?= adm_icon('plus', 18) ?> استوری جدید</a>
    </div>
    <?php if (!setting_on('show_stories')): ?>
        <div class="alert alert-warn">نمایش استوری‌ها در سایت خاموش است. از «ظاهر سایت ← چیدمان صفحه اصلی» روشنش کنید.</div>
    <?php endif; ?>
    <?php if (!$list): ?>
        <div class="empty-state"><span>⭕</span>هنوز استوری‌ای نساخته‌اید.<br><small>استوری‌ها به شکل دایره بالای صفحه اصلی نمایش داده می‌شوند و با لمس، تمام‌صفحه پخش می‌شوند.</small><br><a class="abtn abtn-primary abtn-sm mt" href="story-edit.php">ساخت اولین استوری</a></div>
    <?php else: ?>
    <div class="story-grid">
        <?php foreach ($list as $s):
            $expired = $s['expires_at'] && $s['expires_at'] <= now();
            $on = $s['is_active'] && !$expired && $s['n'];
            $thumb = $s['cover'] ?: ($s['first_type'] === 'image' ? $s['first_media'] : null); ?>
            <div class="story-card<?= $on ? '' : ' off' ?>">
                <a class="sc-ring" href="story-edit.php?id=<?= (int) $s['id'] ?>">
                    <span><?php if ($thumb): ?><img src="<?= e(media_url($thumb)) ?>" alt=""><?php elseif ($s['first_media']): ?><video src="<?= e(media_url($s['first_media'])) ?>#t=0.5" muted playsinline preload="metadata"></video><?php else: ?>⭕<?php endif; ?></span>
                </a>
                <b><?= e($s['title']) ?></b>
                <div class="row-flex" style="gap:4px;justify-content:center">
                    <?= $on ? '<span class="badge b-ok">● در حال نمایش</span>' : ($expired ? '<span class="badge b-warn">منقضی شده</span>' : (!$s['n'] ? '<span class="badge b-muted">بدون اسلاید</span>' : '<span class="badge b-muted">غیرفعال</span>')) ?>
                </div>
                <small class="muted"><?= fa_num((string) $s['n']) ?> اسلاید · <?= icon('eye', 12) ?> <?= fa_short_num((int) $s['views']) ?> بازدید</small>
                <small class="muted"><?= icon('clock', 12) ?> <?= e(story_remaining($s['expires_at'])) ?></small>
                <form method="post" class="row-flex" style="gap:4px;justify-content:center">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <a class="abtn abtn-primary abtn-xs" href="story-edit.php?id=<?= (int) $s['id'] ?>">ویرایش</a>
                    <?php if ($expired): ?><button class="abtn abtn-ok abtn-xs" name="act" value="renew">تمدید ۲۴ ساعت</button>
                    <?php else: ?><button class="abtn abtn-ghost abtn-xs" name="act" value="toggle"><?= $s['is_active'] ? 'غیرفعال' : 'فعال' ?></button><?php endif; ?>
                    <button class="abtn abtn-danger abtn-xs" name="act" value="delete" data-confirm="استوری «<?= e($s['title']) ?>» حذف شود؟">حذف</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php adm_foot();
