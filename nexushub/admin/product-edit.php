<?php
/**
 * فروشگاه — ساخت / ویرایش محصول
 * بسته‌ها (قیمت تومانی، دلاری یا تونی)، مقدار دلخواه، اطلاعات دریافتی از مشتری
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop');

$id = (int) ($_GET['id'] ?? 0);
$types = product_types();
$p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('محصول یافت نشد.', 'error'); redirect('admin/products.php'); }
$preset = (string) ($_GET['type'] ?? 'premium');
if (!isset($types[$preset])) $preset = 'other';
$p = $p ?: ['id' => 0, 'category_id' => null, 'type' => $preset, 'title' => '', 'slug' => '', 'subtitle' => '', 'description' => '', 'image' => '',
    'icon' => $types[$preset]['icon'], 'color' => $types[$preset]['color'], 'badge' => '', 'delivery_note' => '', 'fields' => json_encode($types[$preset]['fields'], JSON_UNESCAPED_UNICODE),
    'custom_amount' => '', 'margin' => null, 'is_featured' => 0, 'status' => 'active', 'sort_order' => 0, 'sales' => 0];
$variants = $p['id'] ? product_variants((int) $p['id'], false) : [];
$errors = [];

if (is_post()) {
    csrf_check();
    $in = fn($k, $max = 255) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $num = fn($v) => ($v = trim(en_num((string) $v))) === '' ? null : (float) str_replace(['٬', ','], '', $v);
    $d = [
        'title' => $in('title', 190), 'subtitle' => $in('subtitle') ?: null, 'type' => isset($types[$_POST['type'] ?? '']) ? $_POST['type'] : 'other',
        'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null, 'description' => sanitize_html((string) ($_POST['description'] ?? '')),
        'image' => $in('image') ?: null, 'icon' => $in('icon', 16) ?: '🛍️', 'color' => valid_hex($in('color', 7), '#ff1f3d'),
        'badge' => $in('badge', 40) ?: null, 'delivery_note' => $in('delivery_note', 190) ?: null,
        'status' => in_array($_POST['status'] ?? '', ['active', 'draft', 'soldout'], true) ? $_POST['status'] : 'draft',
        'is_featured' => !empty($_POST['is_featured']) ? 1 : 0, 'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'margin' => $num($_POST['margin'] ?? ''),
    ];
    if ($d['title'] === '') $errors[] = 'نام محصول را وارد کنید.';
    $slug = slugify($in('slug') ?: $d['title']);
    $d['slug'] = unique_slug('products', $slug ?: 'product', (int) $p['id']);

    // customer fields
    $fields = [];
    foreach ((array) ($_POST['fld'] ?? []) as $f) {
        $label = mb_substr(trim((string) ($f['label'] ?? '')), 0, 80);
        if ($label === '') continue;
        $key = preg_replace('~[^a-z0-9_]~', '', strtolower((string) ($f['key'] ?? ''))) ?: 'f' . substr(md5($label), 0, 6);
        while (in_array($key, array_column($fields, 'key'), true)) $key .= '_2';
        $type = in_array($f['type'] ?? '', ['text', 'username', 'email', 'tel', 'number', 'textarea', 'select'], true) ? $f['type'] : 'text';
        $fields[] = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => !empty($f['required']) ? 1 : 0,
            'placeholder' => mb_substr(trim((string) ($f['placeholder'] ?? '')), 0, 120), 'hint' => mb_substr(trim((string) ($f['hint'] ?? '')), 0, 190),
            'options' => mb_substr(trim((string) ($f['options'] ?? '')), 0, 500)];
    }
    $d['fields'] = json_encode($fields, JSON_UNESCAPED_UNICODE);

    // custom amount
    $c = (array) ($_POST['custom'] ?? []);
    $d['custom_amount'] = json_encode([
        'on' => !empty($c['on']) ? 1 : 0, 'unit' => mb_substr(trim((string) ($c['unit'] ?? '')), 0, 30) ?: 'عدد',
        'price_type' => in_array($c['price_type'] ?? '', ['toman', 'usd', 'ton'], true) ? $c['price_type'] : 'toman',
        'unit_price' => (float) ($num($c['unit_price'] ?? '') ?? 0), 'min' => max(0.000001, (float) ($num($c['min'] ?? '') ?? 1)),
        'max' => max(0.000001, (float) ($num($c['max'] ?? '') ?? 100000)), 'step' => max(0.000001, (float) ($num($c['step'] ?? '') ?? 1)),
        'presets' => mb_substr(en_num(trim((string) ($c['presets'] ?? ''))), 0, 200),
    ], JSON_UNESCAPED_UNICODE);
    $cj = json_decode($d['custom_amount'], true);
    if ($cj['on'] && $cj['unit_price'] <= 0) $errors[] = 'قیمت هر واحد در «مقدار دلخواه» را وارد کنید.';
    if ($cj['on'] && $cj['min'] > $cj['max']) $errors[] = 'در «مقدار دلخواه»، حداقل نباید از حداکثر بیشتر باشد.';

    // packages
    $vin = [];
    foreach ((array) ($_POST['v'] ?? []) as $v) {
        $t = mb_substr(trim((string) ($v['title'] ?? '')), 0, 120);
        if ($t === '') continue;
        $amount = $num($v['amount'] ?? '');
        if ($amount === null || $amount <= 0) { $errors[] = 'قیمت بسته «' . $t . '» را وارد کنید.'; continue; }
        $stock = trim(en_num((string) ($v['stock'] ?? '')));
        $vin[] = ['id' => (int) ($v['id'] ?? 0), 'title' => $t, 'subtitle' => mb_substr(trim((string) ($v['subtitle'] ?? '')), 0, 190) ?: null,
            'price_type' => in_array($v['price_type'] ?? '', ['toman', 'usd', 'ton'], true) ? $v['price_type'] : 'toman',
            'amount' => $amount, 'compare_amount' => $num($v['compare_amount'] ?? ''), 'stock' => $stock === '' ? null : max(0, (int) $stock),
            'badge' => mb_substr(trim((string) ($v['badge'] ?? '')), 0, 40) ?: null, 'is_active' => !empty($v['is_active']) ? 1 : 0];
    }
    if (!$vin && !$cj['on'] && $d['status'] === 'active') $errors[] = 'حداقل یک بسته قیمت اضافه کنید یا «مقدار دلخواه» را روشن کنید.';

    if (!$errors) {
        $d['updated_at'] = now();
        if ($p['id']) update('products', $d, (int) $p['id']);
        else { $d['created_at'] = now(); $p['id'] = insert('products', $d); }
        $keep = [];
        foreach ($vin as $i => $v) {
            $vid = $v['id']; unset($v['id']);
            $v['sort_order'] = $i; $v['product_id'] = (int) $p['id'];
            if ($vid && val('SELECT id FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $p['id']])) update('product_variants', $v, $vid);
            else $vid = insert('product_variants', $v);
            $keep[] = (int) $vid;
        }
        q('DELETE FROM product_variants WHERE product_id = ?' . ($keep ? ' AND id NOT IN (' . implode(',', $keep) . ')' : ''), [$p['id']]);
        flash('محصول ذخیره شد ✅');
        redirect('admin/product-edit.php?id=' . $p['id']);
    }
    // keep what was typed
    $p = array_merge($p, $d);
    $variants = $vin;
}

$custom = product_custom($p);
$fields = product_fields($p);
$cats = rows('SELECT * FROM shop_categories ORDER BY sort_order, id');
$r = shop_rates();
$pt = price_type_names();
$fmt = fn($n) => $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float) $n, 6, '.', ''), '0'), '.');
$ftypes = ['text' => 'متن', 'username' => 'آیدی تلگرام (@username)', 'email' => 'ایمیل', 'tel' => 'شماره تماس', 'number' => 'عدد', 'textarea' => 'متن چندخطی', 'select' => 'انتخاب از لیست'];

adm_head($p['id'] ? 'ویرایش محصول' : 'محصول جدید', 'products');
?>
<?php if ($errors): ?><div class="alert alert-warn mb"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<?php if (!$p['id']): ?>
<div class="card mb">
    <div class="card-head"><h3>⚡ شروع سریع — نوع محصول را انتخاب کنید</h3><span class="muted small">فیلدها و آیکن پیش‌فرض همان نوع پر می‌شود؛ بعداً همه‌چیز قابل تغییر است</span></div>
    <div class="type-grid">
        <?php foreach ($types as $k => $t): ?><a class="type-opt<?= $p['type'] === $k ? ' on' : '' ?>" href="?type=<?= $k ?>" style="--c:<?= e($t['color']) ?>"><span><?= e($t['icon']) ?></span><b><?= e($t['name']) ?></b></a><?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form method="post" id="prodForm" class="split" data-rates='<?= e(json_encode(['usd' => $r['usd'], 'ton' => $r['ton'], 'margin' => (float) setting('shop_margin', '0'), 'round' => max(1, (int) setting('shop_round', '1000'))])) ?>'>
    <?= csrf_field() ?>
    <div>
        <div class="card">
            <div class="card-head"><h3>📦 اطلاعات محصول</h3><?php if ($p['id']): ?><a class="abtn abtn-ghost abtn-sm" href="<?= e(product_url($p)) ?>" target="_blank"><?= icon('eye', 15) ?> مشاهده در سایت</a><?php endif; ?></div>
            <?= f_text('title', 'نام محصول *', $p['title'], ['placeholder' => 'مثلاً: پریمیوم تلگرام', 'required' => true]) ?>
            <div class="grid g2">
                <?= f_text('slug', 'نامک (آدرس)', $p['slug'], ['ltr' => true, 'placeholder' => 'telegram-premium', 'hint' => 'خالی = از روی نام ساخته می‌شود']) ?>
                <?= f_select('type', 'نوع محصول', array_map(fn($t) => $t['icon'] . ' ' . $t['name'], $types), $p['type']) ?>
            </div>
            <?= f_text('subtitle', 'توضیح کوتاه (زیر نام)', (string) $p['subtitle'], ['placeholder' => 'مثلاً: فعال‌سازی روی اکانت خودت، بدون نیاز به رمز']) ?>
            <?= f_textarea('description', 'توضیحات کامل (HTML ساده مجاز است: <b>، <br>، <ul>، <a> ...)', (string) $p['description'], ['rows' => 7]) ?>
        </div>

        <div class="card mt">
            <div class="card-head"><h3>💰 بسته‌ها و قیمت</h3><button type="button" class="abtn abtn-primary abtn-sm" id="addVar"><?= adm_icon('plus', 16) ?> بسته جدید</button></div>
            <p class="muted small">هر بسته می‌تواند قیمت <b>تومانی ثابت</b>، <b>دلاری</b> (× قیمت دلار) یا <b>تونی</b> (× قیمت لحظه‌ای TON × دلار) داشته باشد. قیمت نهایی تومانی خودکار محاسبه و گرد می‌شود.</p>
            <div class="var-list sortable" id="varList">
                <?php foreach ($variants as $i => $v) : ?>
                <div class="var-row sort-item" draggable="true">
                    <span class="sort-handle">⋮⋮</span>
                    <input type="hidden" data-n="id" value="<?= (int) ($v['id'] ?? 0) ?>">
                    <div class="var-grid">
                        <label class="af"><span class="af-label">نام بسته *</span><input data-n="title" value="<?= e($v['title']) ?>" placeholder="مثلاً ۳ ماهه"></label>
                        <label class="af"><span class="af-label">توضیح</span><input data-n="subtitle" value="<?= e((string) $v['subtitle']) ?>" placeholder="اختیاری"></label>
                        <label class="af"><span class="af-label">نوع قیمت</span><select data-n="price_type"><?php foreach ($pt as $k => $n): ?><option value="<?= $k ?>" <?= $v['price_type'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
                        <label class="af"><span class="af-label">قیمت *</span><input data-n="amount" dir="ltr" inputmode="decimal" value="<?= e($fmt($v['amount'])) ?>"></label>
                        <label class="af"><span class="af-label">قیمت قبل از تخفیف</span><input data-n="compare_amount" dir="ltr" inputmode="decimal" value="<?= e($fmt($v['compare_amount'])) ?>" placeholder="اختیاری"></label>
                        <label class="af"><span class="af-label">موجودی</span><input data-n="stock" dir="ltr" inputmode="numeric" value="<?= $v['stock'] === null ? '' : (int) $v['stock'] ?>" placeholder="نامحدود"></label>
                        <label class="af"><span class="af-label">برچسب</span><input data-n="badge" value="<?= e((string) $v['badge']) ?>" placeholder="مثلاً پرفروش"></label>
                        <label class="chk-row var-on"><input type="checkbox" data-n="is_active" value="1" <?= !empty($v['is_active']) ? 'checked' : '' ?>> فعال</label>
                    </div>
                    <div class="var-side"><span class="var-price" data-preview>—</span><button type="button" class="adm-ib danger" data-del title="حذف بسته"><?= adm_icon('trash', 16) ?></button></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="empty-state var-empty" <?= $variants ? 'hidden' : '' ?>><span>💰</span>هنوز بسته‌ای ندارد.<br><small>مثلاً برای پریمیوم: ۳ ماهه، ۶ ماهه، ۱۲ ماهه — یا فقط «مقدار دلخواه» را روشن کنید.</small></div>
        </div>

        <div class="card mt">
            <div class="card-head"><h3>✨ خرید با مقدار دلخواه</h3><span class="muted small">مثل: هر تعداد استارز یا هر مقدار تون</span></div>
            <label class="sw-row"><span class="sw-text"><b>مشتری خودش مقدار را وارد کند</b><small>قیمت = مقدار × قیمت هر واحد (به‌صورت زنده محاسبه می‌شود)</small></span>
                <input type="hidden" name="custom[on]" value="0"><input type="checkbox" class="sw" name="custom[on]" value="1" id="cOn" <?= $custom['on'] ? 'checked' : '' ?>></label>
            <div class="grid g4" id="cBox">
                <?= f_text('custom[unit]', 'واحد', $custom['unit'], ['placeholder' => 'استارز / TON / عدد']) ?>
                <?= f_select('custom[price_type]', 'نوع قیمت واحد', $pt, $custom['price_type'], ['attrs' => 'id="cType"']) ?>
                <?= f_text('custom[unit_price]', 'قیمت هر واحد', $fmt($custom['unit_price']), ['ltr' => true, 'attrs' => 'inputmode="decimal" id="cPrice"', 'hint' => 'مثلاً هر استارز 0.015 دلار']) ?>
                <?= f_text('custom[step]', 'گام (افزایش)', $fmt($custom['step']), ['ltr' => true, 'attrs' => 'inputmode="decimal"']) ?>
                <?= f_text('custom[min]', 'حداقل', $fmt($custom['min']), ['ltr' => true, 'attrs' => 'inputmode="decimal" id="cMin"']) ?>
                <?= f_text('custom[max]', 'حداکثر', $fmt($custom['max']), ['ltr' => true, 'attrs' => 'inputmode="decimal"']) ?>
                <div style="grid-column: span 2"><?= f_text('custom[presets]', 'دکمه‌های سریع (با کاما)', implode(',', array_map($fmt, $custom['presets'])), ['ltr' => true, 'placeholder' => '50,100,250,500,1000']) ?></div>
            </div>
            <div class="alert alert-info small" id="cPreview">—</div>
        </div>

        <div class="card mt">
            <div class="card-head"><h3>📝 اطلاعاتی که از مشتری گرفته می‌شود</h3><button type="button" class="abtn abtn-primary abtn-sm" id="addFld"><?= adm_icon('plus', 16) ?> فیلد جدید</button></div>
            <p class="muted small">مثلاً آیدی تلگرام برای پریمیوم و استارز، آدرس کیف پول برای تون، ایمیل اکانت برای بازی. برای گیفت کارت می‌توانید هیچ فیلدی نگذارید.</p>
            <div class="fld-list sortable" id="fldList">
                <?php foreach ($fields as $f): ?>
                <div class="fld-row sort-item" draggable="true">
                    <span class="sort-handle">⋮⋮</span>
                    <div class="fld-grid">
                        <label class="af"><span class="af-label">عنوان فیلد *</span><input data-n="label" value="<?= e($f['label']) ?>"></label>
                        <label class="af"><span class="af-label">نوع</span><select data-n="type"><?php foreach ($ftypes as $k => $n): ?><option value="<?= $k ?>" <?= ($f['type'] ?? 'text') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
                        <label class="af"><span class="af-label">متن راهنما داخل فیلد</span><input data-n="placeholder" value="<?= e($f['placeholder'] ?? '') ?>"></label>
                        <label class="af fld-opts"><span class="af-label">گزینه‌ها (با کاما)</span><input data-n="options" value="<?= e($f['options'] ?? '') ?>" placeholder="مثلاً: آسیا,اروپا,آمریکا"></label>
                        <label class="af"><span class="af-label">توضیح زیر فیلد</span><input data-n="hint" value="<?= e($f['hint'] ?? '') ?>"></label>
                        <input type="hidden" data-n="key" value="<?= e($f['key']) ?>">
                        <label class="chk-row"><input type="checkbox" data-n="required" value="1" <?= !empty($f['required']) ? 'checked' : '' ?>> اجباری</label>
                    </div>
                    <button type="button" class="adm-ib danger" data-del title="حذف فیلد"><?= adm_icon('trash', 16) ?></button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="split-side">
        <div class="card">
            <div class="card-head"><h3>🚀 انتشار</h3></div>
            <?= f_select('status', 'وضعیت', ['active' => '● فعال (قابل خرید)', 'soldout' => 'ناموجود (نمایش بدون خرید)', 'draft' => 'پیش‌نویس (مخفی)'], $p['status']) ?>
            <?= f_switch('is_featured', '⭐ محصول ویژه', (bool) $p['is_featured'], 'اول فروشگاه و در بنر صفحه اصلی نمایش داده می‌شود') ?>
            <?= f_select('category_id', 'دسته فروشگاه', ['0' => '— بدون دسته —'] + array_column(array_map(fn($c) => ['id' => $c['id'], 'n' => $c['icon'] . ' ' . $c['name']], $cats), 'n', 'id'), (string) (int) $p['category_id'], ['hint' => '<a href="shop-categories.php">مدیریت دسته‌ها</a>']) ?>
            <?= f_text('sort_order', 'ترتیب نمایش', (string) $p['sort_order'], ['type' => 'number', 'ltr' => true]) ?>
            <button class="abtn abtn-primary abtn-block" type="submit"><?= icon('check', 18) ?> ذخیره محصول</button>
        </div>
        <div class="card mt">
            <div class="card-head"><h3>🎨 ظاهر</h3></div>
            <div class="logo-pick"><?= f_image('image', 'تصویر محصول (اختیاری)', $p['image'], 'بدون تصویر، آیکن ایموجی با هاله رنگی نمایش داده می‌شود') ?></div>
            <div class="grid g2">
                <?= f_text('icon', 'آیکن (ایموجی)', $p['icon'], ['maxlength' => 16]) ?>
                <?= f_color('color', 'رنگ', $p['color']) ?>
            </div>
            <?= f_text('badge', 'برچسب روی کارت', (string) $p['badge'], ['placeholder' => 'مثلاً: پرفروش 🔥 / تخفیف']) ?>
            <?= f_text('delivery_note', 'زمان / نحوه تحویل', (string) $p['delivery_note'], ['placeholder' => 'مثلاً: تحویل ۵ تا ۳۰ دقیقه']) ?>
        </div>
        <div class="card mt">
            <div class="card-head"><h3>📈 سود این محصول</h3></div>
            <?= f_text('margin', 'درصد سود (خالی = پیش‌فرض فروشگاه: ' . fa_num((string) (float) setting('shop_margin', '0')) . '٪)', $p['margin'] === null ? '' : $fmt($p['margin']), ['ltr' => true, 'attrs' => 'inputmode="decimal" id="pMargin"', 'hint' => 'فقط روی قیمت‌های دلاری و تونی اعمال می‌شود']) ?>
            <div class="rate-mini">
                <span>💵 دلار: <b><?= $r['usd'] ? money((int) $r['usd']) . ' ت' : 'تنظیم نشده' ?></b></span>
                <span>💎 TON: <b dir="ltr"><?= $r['ton'] ? '$' . number_format($r['ton'], 3) : '—' ?></b></span>
                <a href="shop-settings.php?tab=price">تنظیم قیمت‌ها ←</a>
            </div>
        </div>
    </div>
</form>

<template id="varTpl">
    <div class="var-row sort-item" draggable="true">
        <span class="sort-handle">⋮⋮</span>
        <input type="hidden" data-n="id" value="0">
        <div class="var-grid">
            <label class="af"><span class="af-label">نام بسته *</span><input data-n="title" placeholder="مثلاً ۳ ماهه"></label>
            <label class="af"><span class="af-label">توضیح</span><input data-n="subtitle" placeholder="اختیاری"></label>
            <label class="af"><span class="af-label">نوع قیمت</span><select data-n="price_type"><?php foreach ($pt as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
            <label class="af"><span class="af-label">قیمت *</span><input data-n="amount" dir="ltr" inputmode="decimal"></label>
            <label class="af"><span class="af-label">قیمت قبل از تخفیف</span><input data-n="compare_amount" dir="ltr" inputmode="decimal" placeholder="اختیاری"></label>
            <label class="af"><span class="af-label">موجودی</span><input data-n="stock" dir="ltr" inputmode="numeric" placeholder="نامحدود"></label>
            <label class="af"><span class="af-label">برچسب</span><input data-n="badge" placeholder="مثلاً پرفروش"></label>
            <label class="chk-row var-on"><input type="checkbox" data-n="is_active" value="1" checked> فعال</label>
        </div>
        <div class="var-side"><span class="var-price" data-preview>—</span><button type="button" class="adm-ib danger" data-del title="حذف بسته"><?= adm_icon('trash', 16) ?></button></div>
    </div>
</template>
<template id="fldTpl">
    <div class="fld-row sort-item" draggable="true">
        <span class="sort-handle">⋮⋮</span>
        <div class="fld-grid">
            <label class="af"><span class="af-label">عنوان فیلد *</span><input data-n="label" placeholder="مثلاً: آیدی تلگرام"></label>
            <label class="af"><span class="af-label">نوع</span><select data-n="type"><?php foreach ($ftypes as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
            <label class="af"><span class="af-label">متن راهنما داخل فیلد</span><input data-n="placeholder"></label>
            <label class="af fld-opts"><span class="af-label">گزینه‌ها (با کاما)</span><input data-n="options" placeholder="مثلاً: آسیا,اروپا,آمریکا"></label>
            <label class="af"><span class="af-label">توضیح زیر فیلد</span><input data-n="hint"></label>
            <input type="hidden" data-n="key" value="">
            <label class="chk-row"><input type="checkbox" data-n="required" value="1" checked> اجباری</label>
        </div>
        <button type="button" class="adm-ib danger" data-del title="حذف فیلد"><?= adm_icon('trash', 16) ?></button>
    </div>
</template>
<?php adm_foot(['shop-admin.js']); ?>
