<?php
/**
 * فروشگاه — فهرست محصولات
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'status':
            $st = (string) ($_POST['status'] ?? '');
            if (in_array($st, ['active', 'draft', 'soldout'], true)) { q('UPDATE products SET status = ?, updated_at = ? WHERE id = ?', [$st, now(), $id]); flash('وضعیت محصول تغییر کرد.'); }
            break;
        case 'feature': q('UPDATE products SET is_featured = 1 - is_featured WHERE id = ?', [$id]); flash('تغییر ذخیره شد.'); break;
        case 'duplicate':
            $p = row('SELECT * FROM products WHERE id = ?', [$id]);
            if ($p) {
                $vars = product_variants($id, false);
                unset($p['id']);
                $p['title'] .= ' (کپی)'; $p['slug'] = unique_slug('products', $p['slug'] . '-copy'); $p['status'] = 'draft'; $p['sales'] = 0; $p['created_at'] = $p['updated_at'] = now();
                $nid = insert('products', $p);
                foreach ($vars as $v) { unset($v['id']); $v['product_id'] = $nid; insert('product_variants', $v); }
                flash('کپی محصول ساخته شد (پیش‌نویس).');
                redirect('admin/product-edit.php?id=' . $nid);
            }
            break;
        case 'delete':
            if ((int) val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id])) {
                q("UPDATE products SET status = 'draft' WHERE id = ?", [$id]);
                flash('این محصول سفارش ثبت‌شده دارد؛ به‌جای حذف، پیش‌نویس (مخفی) شد.', 'warn');
            } else {
                q('DELETE FROM product_variants WHERE product_id = ?', [$id]); q('DELETE FROM products WHERE id = ?', [$id]); flash('محصول حذف شد.');
            }
            break;
        case 'sort':
            foreach ((array) ($_POST['ids'] ?? []) as $i => $pid) q('UPDATE products SET sort_order = ? WHERE id = ?', [$i, (int) $pid]);
            json_out(['ok' => true]);
    }
    redirect('admin/products.php' . (isset($_GET['cat']) ? '?cat=' . (int) $_GET['cat'] : ''));
}

$catF = (int) ($_GET['cat'] ?? 0);
$list = rows('SELECT p.*, c.name AS cat_name, (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS nvar
    FROM products p LEFT JOIN shop_categories c ON c.id = p.category_id' . ($catF ? ' WHERE p.category_id = ' . $catF : '') . ' ORDER BY p.sort_order, p.id DESC');
$cats = rows('SELECT * FROM shop_categories ORDER BY sort_order, id');
$types = product_types();
$r = shop_rates();
$stats = row("SELECT COUNT(*) total, SUM(status = 'active') active FROM products");
$sales = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) s FROM orders WHERE status = 'completed'");

adm_head('محصولات فروشگاه', 'products');
?>
<div class="grid g4 stats-grid mb">
    <div class="stat"><span class="stat-ic"><?= adm_icon('bag', 24) ?></span><div><b><?= fa_num((string) (int) $stats['total']) ?></b><small>محصول · <?= fa_num((string) (int) $stats['active']) ?> فعال</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3ddc84,#1de9b6);--sh:rgba(61,220,132,.7);--c:rgba(61,220,132,.3)"><span class="stat-ic"><?= icon('check', 24) ?></span><div><b><?= fa_num((string) (int) $sales['n']) ?></b><small>سفارش انجام‌شده · <?= money((int) $sales['s']) ?> تومان</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#0098ea,#3dbbff);--sh:rgba(0,152,234,.7);--c:rgba(0,152,234,.3)"><span class="stat-ic">💎</span><div><b dir="ltr"><?= $r['ton'] ? '$' . fa_num(number_format($r['ton'], 2)) : '—' ?></b><small>قیمت TON · <?= e($r['ton_src']) ?></small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#ffc53d,#ff7a1f);--sh:rgba(255,197,61,.7);--c:rgba(255,197,61,.3)"><span class="stat-ic">💵</span><div><b><?= $r['usd'] ? money((int) $r['usd']) : '—' ?></b><small>دلار (تومان) · <?= e($r['usd_src']) ?></small></div></div>
</div>
<?php if (!$r['usd']): ?><div class="alert alert-warn mb">⚠️ قیمت دلار تنظیم نشده؛ محصولاتی که قیمتشان دلاری یا تونی است «قیمت به‌زودی» نمایش داده می‌شوند. از <a href="shop-settings.php?tab=price">تنظیمات فروشگاه ← قیمت‌گذاری</a> قیمت دلار را وارد کنید.</div><?php endif; ?>

<div class="card">
    <div class="card-head">
        <h3><?= adm_icon('bag', 20) ?> محصولات <small class="muted">(برای مرتب‌سازی بکشید)</small></h3>
        <div class="row-flex">
            <a class="abtn abtn-ghost abtn-sm" href="shop-categories.php"><?= adm_icon('folder', 16) ?> دسته‌های فروشگاه</a>
            <a class="abtn abtn-ghost abtn-sm" href="<?= e(url('shop')) ?>" target="_blank"><?= icon('external', 15) ?> مشاهده فروشگاه</a>
            <a class="abtn abtn-primary" href="product-edit.php"><?= adm_icon('plus', 18) ?> محصول جدید</a>
        </div>
    </div>
    <?php if ($cats): ?>
    <div class="row-flex mb" style="gap:6px">
        <a class="abtn abtn-xs <?= !$catF ? 'abtn-primary' : 'abtn-ghost' ?>" href="products.php">همه</a>
        <?php foreach ($cats as $c): ?><a class="abtn abtn-xs <?= $catF === (int) $c['id'] ? 'abtn-primary' : 'abtn-ghost' ?>" href="?cat=<?= (int) $c['id'] ?>"><?= e($c['icon'] . ' ' . $c['name']) ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$list): ?>
        <div class="empty-state"><span>🛍️</span>هنوز محصولی نساخته‌اید.<br><small>پریمیوم تلگرام، استارز، تون، گیفت کارت، بازی یا هر چیز دیگری — با قیمت تومانی، دلاری یا تونی.</small><br><a class="abtn abtn-primary abtn-sm mt" href="product-edit.php">ساخت اولین محصول</a></div>
    <?php else: ?>
    <div class="sortable prod-list" id="prodList">
        <?php foreach ($list as $p): $t = $types[$p['type']] ?? $types['other']; $from = product_from_price($p); ?>
        <div class="sort-item prod-row" draggable="true" data-id="<?= (int) $p['id'] ?>">
            <span class="sort-handle">⋮⋮</span>
            <span class="prod-thumb" style="--c:<?= e($p['color']) ?>"><?php if ($p['image']): ?><img src="<?= e(media_url($p['image'])) ?>" alt=""><?php else: ?><?= e($p['icon']) ?><?php endif; ?></span>
            <div class="prod-info">
                <a href="product-edit.php?id=<?= (int) $p['id'] ?>"><b><?= e($p['title']) ?></b></a>
                <small><?= e($t['icon'] . ' ' . $t['name']) ?><?= $p['cat_name'] ? ' · ' . e($p['cat_name']) : '' ?> · <?= fa_num((string) $p['nvar']) ?> بسته<?= product_custom($p)['on'] ? ' + مقدار دلخواه' : '' ?> · <?= fa_num((string) $p['sales']) ?> فروش</small>
            </div>
            <span class="prod-price"><?= $from !== null ? 'از ' . money($from) . ' ت' : '<span class="muted">بدون قیمت</span>' ?></span>
            <span class="prod-badges">
                <?= ['active' => '<span class="badge b-ok">● فعال</span>', 'draft' => '<span class="badge b-muted">پیش‌نویس</span>', 'soldout' => '<span class="badge b-warn">ناموجود</span>'][$p['status']] ?? '' ?>
                <?php if ($p['is_featured']): ?><span class="badge b-hot">⭐ ویژه</span><?php endif; ?>
            </span>
            <div class="row-flex prod-acts">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="act" value="status">
                    <select name="status" onchange="this.form.submit()" class="sel-xs" aria-label="وضعیت">
                        <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>فعال</option>
                        <option value="soldout" <?= $p['status'] === 'soldout' ? 'selected' : '' ?>>ناموجود</option>
                        <option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                    </select></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="adm-ib" name="act" value="feature" title="ویژه / عادی"><?= adm_icon('star', 16) ?></button></form>
                <a class="adm-ib" href="product-edit.php?id=<?= (int) $p['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
                <a class="adm-ib" href="<?= e(product_url($p)) ?>" target="_blank" title="مشاهده"><?= icon('eye', 16) ?></a>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="adm-ib" name="act" value="duplicate" title="کپی محصول"><?= icon('copy', 16) ?></button></form>
                <form method="post" onsubmit="return confirm('محصول حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="adm-ib danger" name="act" value="delete" title="حذف"><?= adm_icon('trash', 16) ?></button></form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('prodList');
    if (!list) return;
    var t;
    NXA.sortable(list, save);
    function save() {
        clearTimeout(t);
        t = setTimeout(function () {
            var fd = new FormData();
            fd.append('_csrf', NXA.csrf); fd.append('act', 'sort');
            list.querySelectorAll('.prod-row').forEach(function (r) { fd.append('ids[]', r.dataset.id); });
            fetch('products.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () { NXA.toast('ترتیب ذخیره شد ✅'); });
        }, 300);
    }
});
</script>
<?php adm_foot(); ?>
