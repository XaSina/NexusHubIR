<?php
require __DIR__ . '/inc/boot.php';
require_cap('categories');

$edit = (int) ($_GET['edit'] ?? 0);
$c = $edit ? row('SELECT * FROM categories WHERE id = ?', [$edit]) : null;
$c = $c ?: ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'color' => '#ff1f3d', 'icon' => '📰', 'sort_order' => (int) val('SELECT COALESCE(MAX(sort_order),0)+1 FROM categories'), 'show_on_home' => 1, 'show_in_header' => 0, 'show_in_mobile' => 1, 'show_in_footer' => 1];

if (is_post()) {
    csrf_check();
    if (!empty($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        q('UPDATE posts SET category_id = NULL WHERE category_id = ?', [$did]);
        q('DELETE FROM categories WHERE id = ?', [$did]);
        flash('دسته‌بندی حذف شد. اخبار آن بدون دسته شدند.');
        redirect('admin/categories.php');
    }
    if (!empty($_POST['toggle']) && in_array($_POST['toggle'], ['show_in_header', 'show_in_mobile', 'show_in_footer', 'show_on_home'], true)) {
        $col = $_POST['toggle'];
        q("UPDATE categories SET `$col` = 1 - `$col` WHERE id = ?", [(int) ($_POST['cid'] ?? 0)]);
        flash('نمایش دسته بروزرسانی شد.');
        redirect('admin/categories.php');
    }
    if (!empty($_POST['order'])) {
        foreach (explode(',', (string) $_POST['order']) as $i => $oid) q('UPDATE categories SET sort_order = ? WHERE id = ?', [$i, (int) $oid]);
        flash('ترتیب دسته‌ها ذخیره شد.');
        redirect('admin/categories.php');
    }
    $data = [
        'name' => mb_substr(trim((string) $_POST['name']), 0, 120),
        'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500),
        'color' => valid_hex((string) ($_POST['color'] ?? ''), '#ff1f3d'),
        'icon' => mb_substr(trim((string) ($_POST['icon'] ?? '📰')), 0, 8) ?: '📰',
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'show_on_home' => !empty($_POST['show_on_home']) ? 1 : 0,
        'show_in_header' => !empty($_POST['show_in_header']) ? 1 : 0,
        'show_in_mobile' => !empty($_POST['show_in_mobile']) ? 1 : 0,
        'show_in_footer' => !empty($_POST['show_in_footer']) ? 1 : 0,
    ];
    if ($data['name'] === '') { flash('نام دسته را وارد کنید.', 'error'); redirect('admin/categories.php' . ($c['id'] ? '?edit=' . $c['id'] : '')); }
    $data['slug'] = unique_slug('categories', slugify(trim((string) ($_POST['slug'] ?? '')) ?: $data['name']), (int) $c['id']);
    if ($c['id']) { update('categories', $data, (int) $c['id']); flash('دسته‌بندی بروزرسانی شد.'); }
    else { $data['created_at'] = now(); insert('categories', $data); flash('دسته‌بندی جدید ساخته شد.'); }
    redirect('admin/categories.php');
}

$cats = get_categories(true);
adm_head('دسته‌بندی‌ها', 'categories');
?>
<div class="split">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('folder', 20) ?> لیست دسته‌ها</h3><span class="muted small">برای تغییر ترتیب بکشید و رها کنید</span></div>
        <form method="post" id="orderForm"><?= csrf_field() ?><input type="hidden" name="order" id="orderInput"></form>
        <div class="sortable" id="catList">
            <?php foreach ($cats as $k): ?>
                <div class="sort-item" draggable="true" data-id="<?= (int) $k['id'] ?>">
                    <span class="sort-handle">⋮⋮</span>
                    <span class="cat-dot" style="--c:<?= e($k['color']) ?>"><?= e($k['icon']) ?></span>
                    <b><?= e($k['name']) ?> <small class="muted ltr">/<?= e($k['slug']) ?></small></b>
                    <span class="badge"><?= fa_num((string) $k['cnt']) ?> خبر</span>
                    <form method="post" class="cat-places"><?= csrf_field() ?><input type="hidden" name="cid" value="<?= (int) $k['id'] ?>">
                        <?php foreach (['show_in_header' => ['🖥️', 'منوی کامپیوتر'], 'show_in_mobile' => ['📱', 'منوی موبایل'], 'show_in_footer' => ['⬇️', 'فوتر'], 'show_on_home' => ['🏠', 'بلوک صفحه اصلی']] as $col => [$ic, $lbl]): ?>
                            <button class="cp<?= $k[$col] ? ' on' : '' ?>" name="toggle" value="<?= $col ?>" title="<?= e($lbl) ?>: <?= $k[$col] ? 'روشن — برای خاموش کردن کلیک کنید' : 'خاموش — برای روشن کردن کلیک کنید' ?>"><?= $ic ?></button>
                        <?php endforeach; ?>
                    </form>
                    <a class="adm-ib" href="?edit=<?= (int) $k['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
                    <form method="post" style="display:inline"><?= csrf_field() ?><button class="adm-ib" name="delete_id" value="<?= (int) $k['id'] ?>" data-confirm="دسته «<?= e($k['name']) ?>» حذف شود؟"><?= adm_icon('trash', 16) ?></button></form>
                </div>
            <?php endforeach; ?>
            <?php if (!$cats): ?><div class="empty-state"><span>📁</span>هنوز دسته‌ای ساخته نشده.</div><?php endif; ?>
        </div>
        <button class="abtn abtn-ghost abtn-sm mt" id="saveOrder" hidden>ذخیره ترتیب</button>
    </div>
    <div class="split-side">
        <form class="card" method="post">
            <?= csrf_field() ?>
            <div class="card-head"><h3><?= adm_icon($c['id'] ? 'edit' : 'plus', 20) ?> <?= $c['id'] ? 'ویرایش دسته' : 'دسته جدید' ?></h3><?php if ($c['id']): ?><a class="abtn abtn-ghost abtn-xs" href="categories.php">لغو</a><?php endif; ?></div>
            <?= f_text('name', 'نام دسته', $c['name'], ['required' => true, 'id' => 'cName']) ?>
            <?= f_text('slug', 'نامک (انگلیسی پیشنهاد می‌شود)', $c['slug'], ['ltr' => true, 'attrs' => 'data-slug-from="#cName"']) ?>
            <div class="grid g2">
                <?= f_text('icon', 'ایموجی / آیکون', $c['icon'], ['maxlength' => 8]) ?>
                <?= f_color('color', 'رنگ', $c['color']) ?>
            </div>
            <div class="row-flex mb" style="gap:6px">
                <?php foreach (['🎮', '💻', '🎬', '🎵', '🎉', '📰', '📱', '🕹️', '🎧', '🍿', '⚽', '🚀', '🤖', '💡', '🔥', '🏆'] as $em): ?>
                    <button type="button" class="adm-ib" onclick="this.form.icon.value='<?= $em ?>'"><?= $em ?></button>
                <?php endforeach; ?>
            </div>
            <?= f_textarea('description', 'توضیحات', (string) $c['description'], ['rows' => 3]) ?>
            <?= f_text('sort_order', 'ترتیب', (string) $c['sort_order'], ['type' => 'number']) ?>
            <div class="af-label" style="margin:10px 0 2px">کجا نمایش داده شود؟</div>
            <?= f_switch('show_in_header', '🖥️ منوی بالای سایت (کامپیوتر)', (bool) $c['show_in_header'], 'کنار «خانه» و «همه اخبار» در نوار بالا؛ روی موبایل نمایش داده نمی‌شود') ?>
            <?= f_switch('show_in_mobile', '📱 منوی موبایل', (bool) $c['show_in_mobile'], 'داخل منوی کشویی موبایل (دکمه ☰)') ?>
            <?= f_switch('show_in_footer', '⬇️ فوتر', (bool) $c['show_in_footer'], 'ستون «دسته‌بندی‌ها» پایین سایت') ?>
            <?= f_switch('show_on_home', '🏠 بلوک در صفحه اصلی', (bool) $c['show_on_home'], 'ردیف اخبار این دسته در صفحه اصلی') ?>
            <button class="abtn abtn-primary abtn-block mt" type="submit"><?= $c['id'] ? 'ذخیره تغییرات' : 'افزودن دسته' ?></button>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('catList'), btn = document.getElementById('saveOrder');
    NXA.sortable(list, function () { btn.hidden = false; });
    btn.addEventListener('click', function () {
        document.getElementById('orderInput').value = [].map.call(list.querySelectorAll('.sort-item'), function (i) { return i.dataset.id; }).join(',');
        document.getElementById('orderForm').submit();
    });
});
</script>
<?php adm_foot();
