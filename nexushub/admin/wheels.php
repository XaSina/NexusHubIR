<?php
require __DIR__ . '/inc/boot.php';
require_cap('wheels');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (prize_claim_save()) redirect('admin/wheels.php');
    if (($_POST['act'] ?? '') === 'opts') { save_settings(['footer_wheel_link' => !empty($_POST['footer_wheel_link']) ? '1' : '0']); flash('تنظیمات ذخیره شد ✅'); redirect('admin/wheels.php'); }
    if (($_POST['act'] ?? '') === 'toggle' && ($tw = row('SELECT * FROM wheels WHERE id = ?', [$id]))) {
        q('UPDATE wheels SET status = ?, updated_at = ? WHERE id = ?', [$tw['status'] === 'active' ? 'paused' : 'active', now(), $id]);
        flash($tw['status'] === 'active' ? 'گردونه غیرفعال شد — هر کی لینکش رو باز کنه می‌بینه بسته‌ست.' : 'گردونه فعال شد 🎡');
        redirect('admin/wheels.php');
    }
    if (($_POST['act'] ?? '') === 'delete' && row('SELECT id FROM wheels WHERE id = ?', [$id])) {
        foreach (['wheel_spins', 'wheel_refs', 'wheel_grants'] as $t) q("DELETE FROM `$t` WHERE wheel_id = ?", [$id]);
        q('DELETE FROM wheels WHERE id = ?', [$id]);
        flash('گردونه حذف شد.');
    }
    redirect('admin/wheels.php');
}

$list = rows("SELECT w.*, (SELECT COUNT(*) FROM wheel_spins s WHERE s.wheel_id = w.id AND s.member_id IS NOT NULL) spins,
    (SELECT COUNT(*) FROM wheel_spins s WHERE s.wheel_id = w.id AND s.member_id IS NOT NULL AND s.kind = 'prize' AND s.status = 'new') todo
    FROM wheels w ORDER BY (w.status = 'active') DESC, w.id DESC");
$badge = ['draft' => ['b-muted', 'پیش‌نویس'], 'active' => ['b-ok', 'فعال'], 'paused' => ['b-warn', 'متوقف']];
adm_head('گردونه شانس', 'wheels');
?>
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🎡</span><div><h2>گردونه شانس</h2><small>جدا از قرعه‌کشی — گزینه‌ها و شانس‌ها رو خودت تعیین می‌کنی</small></div></div>
    <div class="row-flex">
        <a class="rfl-new rfl-new-alt" href="wheel-edit.php?audience=private"><span>🔒</span> گردونه‌ی شخصی</a>
        <a class="rfl-new" href="wheel-edit.php"><span><?= adm_icon('plus', 18) ?></span> گردونه برای سایت</a>
    </div>
</div>
<?= prize_claim_card() ?>
<?php if (!$list): ?>
    <div class="card"><div class="empty-state"><span>🎡</span>هنوز گردونه‌ای نساختی.<br><small class="muted">«گردونه برای سایت»: کاربرها با ثبت‌نام، خرید و دعوت چرخش می‌گیرن · «گردونه‌ی شخصی»: فقط خودت توی پنل می‌چرخونی.</small></div></div>
<?php else: ?>
<div class="rfl-grid">
    <?php foreach ($list as $i => $w): $items = wheel_items($w); $pz = array_values(array_filter($items, fn($it) => $it['kind'] === 'prize')); $private = $w['audience'] === 'private'; $b = $badge[$w['status']] ?? ['', $w['status']]; ?>
    <article class="rfl-card rfl-<?= $private ? 'private' : e($w['status']) ?>" style="--i:<?= $i ?>">
        <a class="rfl-top" href="wheel-view.php?id=<?= (int) $w['id'] ?>">
            <span class="rfl-art"><?php if ($w['image']): ?><img src="<?= e(media_url($w['image'])) ?>" alt=""><?php else: ?>🎡<?php endif; ?></span>
            <span class="rfl-t"><b><?= e($w['title']) ?></b><small>🎁 <?= e(implode('، ', array_slice(array_column($pz, 'label'), 0, 3)) ?: '—') ?></small></span>
            <?php if ($private): ?><span class="badge">🔒 شخصی</span><?php else: ?><span class="badge <?= $b[0] ?>"><?= e($b[1]) ?></span><?php endif; ?>
        </a>
        <div class="rfl-meta">
            <span>🎯 <?= fa_num((string) count($items)) ?> گزینه</span>
            <?php if (!$private): ?><span>🎡 <?= fa_num((string) $w['spins']) ?> چرخش</span><?php if ($w['todo']): ?><span class="rfl-warn">📦 <?= fa_num((string) $w['todo']) ?> جایزه منتظر تحویل</span><?php endif; ?><?php endif; ?>
        </div>
        <div class="lw-chances"><?php foreach (array_slice($items, 0, 6) as $it): ?><span style="--p:<?= (float) $it['chance'] ?>"><i></i><?= e($it['emoji']) ?> <?= e($it['label']) ?> <b><?= fa_num((string) (float) $it['chance']) ?>٪</b></span><?php endforeach; ?></div>
        <div class="rfl-act">
            <a class="rfl-go" href="wheel-view.php?id=<?= (int) $w['id'] ?>">🎡 <?= $private ? 'بچرخون' : 'مدیریت و چرخوندن' ?></a>
            <?php if (!$private): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="rfl-ib rfl-onoff<?= $w['status'] === 'active' ? ' is-on' : '' ?>" type="submit" title="<?= $w['status'] === 'active' ? 'غیرفعال کن' : 'فعال کن' ?>"><span class="rfl-sw"><i></i></span></button></form><?php endif; ?>
            <a class="rfl-ib" href="wheel-edit.php?id=<?= (int) $w['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
            <form method="post" data-confirm="این گردونه و همه‌ی تاریخچه‌ش حذف بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>"><button class="rfl-ib rfl-del" type="submit" title="حذف"><?= adm_icon('trash', 16) ?></button></form>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php adm_foot();
