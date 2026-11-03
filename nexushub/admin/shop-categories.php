<?php
/**
 * فروشگاه — دسته‌بندی‌ها
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop');

$edit = (int) ($_GET['edit'] ?? 0);
$c = $edit ? row('SELECT * FROM shop_categories WHERE id = ?', [$edit]) : null;
$c = $c ?: ['id' => 0, 'name' => '', 'slug' => '', 'icon' => '🛍️', 'color' => '#ff1f3d', 'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order),0)+1 FROM shop_categories'), 'is_active' => 1];

if (is_post()) {
    csrf_check();
    if (($_POST['act'] ?? '') === 'delete') {
        $id = (int) $_POST['id'];
        q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
        q('DELETE FROM shop_categories WHERE id = ?', [$id]);
        flash('دسته حذف شد (محصولاتش بدون دسته شدند).');
        redirect('admin/shop-categories.php');
    }
    if (($_POST['act'] ?? '') === 'toggle') { q('UPDATE shop_categories SET is_active = 1 - is_active WHERE id = ?', [(int) $_POST['id']]); redirect('admin/shop-categories.php'); }
    $d = [
        'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120),
        'icon' => mb_substr(trim((string) ($_POST['icon'] ?? '')), 0, 16) ?: '🛍️',
        'color' => valid_hex((string) ($_POST['color'] ?? ''), '#ff1f3d'),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($d['name'] === '') { flash('نام دسته را وارد کنید.', 'error'); redirect('admin/shop-categories.php' . ($c['id'] ? '?edit=' . $c['id'] : '')); }
    $d['slug'] = unique_slug('shop_categories', slugify(trim((string) ($_POST['slug'] ?? '')) ?: $d['name']) ?: 'cat', (int) $c['id']);
    if ($c['id']) update('shop_categories', $d, (int) $c['id']);
    else insert('shop_categories', $d + ['created_at' => now()]);
    flash('دسته ذخیره شد ✅');
    redirect('admin/shop-categories.php');
}

$list = rows('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM shop_categories c ORDER BY sort_order, id');
adm_head('دسته‌های فروشگاه', 'products');
?>
<div class="split">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('folder', 20) ?> دسته‌های فروشگاه</h3><a class="abtn abtn-ghost abtn-sm" href="products.php">← محصولات</a></div>
        <?php if (!$list): ?>
            <div class="empty-state"><span>🗂️</span>دسته‌ای ندارید.<br><small>مثلاً: تلگرام ⭐ / کریپتو 💎 / گیفت کارت 🎁 / بازی 🎮</small></div>
        <?php else: ?>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>دسته</th><th>نامک</th><th>محصولات</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $x): ?>
                <tr>
                    <td><span class="cat-dot" style="background:<?= e($x['color']) ?>"></span> <?= e($x['icon']) ?> <b><?= e($x['name']) ?></b></td>
                    <td class="ltr"><?= e($x['slug']) ?></td>
                    <td><?= fa_num((string) $x['n']) ?></td>
                    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="badge <?= $x['is_active'] ? 'b-ok' : 'b-muted' ?>" name="act" value="toggle" style="cursor:pointer"><?= $x['is_active'] ? '● نمایش' : 'مخفی' ?></button></form></td>
                    <td><div class="actions">
                        <a class="adm-ib" href="?edit=<?= (int) $x['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
                        <form method="post" onsubmit="return confirm('دسته حذف شود؟')" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="adm-ib danger" name="act" value="delete" title="حذف"><?= adm_icon('trash', 16) ?></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <form class="card split-side" method="post">
        <?= csrf_field() ?>
        <div class="card-head"><h3><?= $c['id'] ? '✏️ ویرایش دسته' : '➕ دسته جدید' ?></h3></div>
        <?= f_text('name', 'نام دسته *', $c['name'], ['required' => true]) ?>
        <?= f_text('slug', 'نامک', $c['slug'], ['ltr' => true]) ?>
        <div class="grid g2"><?= f_text('icon', 'آیکن', $c['icon'], ['attrs' => 'data-emoji']) ?><?= f_color('color', 'رنگ', $c['color']) ?></div>
        <?= f_text('sort_order', 'ترتیب', (string) $c['sort_order'], ['type' => 'number', 'ltr' => true]) ?>
        <?= f_switch('is_active', 'نمایش در فروشگاه', (bool) $c['is_active']) ?>
        <button class="abtn abtn-primary abtn-block" type="submit"><?= icon('check', 18) ?> ذخیره</button>
        <?php if ($c['id']): ?><a class="abtn abtn-ghost abtn-block mt" href="shop-categories.php">انصراف</a><?php endif; ?>
    </form>
</div>
<?php adm_foot(); ?>
