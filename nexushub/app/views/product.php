<?php
/** @var array $product @var array $formErr @var string $formMsg @var array $old */
$p = $product;
$types = product_types();
$type = $types[$p['type']] ?? $types['other'];
$variants = product_variants((int) $p['id']);
$custom = product_custom($p);
$cjs = custom_price_js($p);
$fields = product_fields($p);
$sold = $p['status'] === 'soldout' || (!$variants && !$custom['on']);
$selVid = (int) ($old['vid'] ?? 0);
if (!$selVid) foreach ($variants as $v) { if ($v['stock'] === null || (int) $v['stock'] > 0) { $selVid = (int) $v['id']; break; } }
$useCustom = $custom['on'] && (!$variants || ($old['vid'] ?? '') === '0');
$related = shop_products(['category_id' => $p['category_id'] ?: 0, 'limit' => 5]);
$related = array_values(array_filter($related, fn($x) => $x['id'] != $p['id']));
$fmtNum = fn(float $n) => rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
$dmode = product_delivery($p);
$unit = product_unit($p);
[$qMin, $qMax] = product_qty_limits($p);
$gallery = product_gallery($p);
$specs = product_specs($p);
if ($dmode === 'codes' && !product_codes($p)) $sold = true;
$dText = trim((string) $p['delivery_note']) ?: ['ship' => 'ارسال پستی به سراسر کشور', 'codes' => 'تحویل خودکار و فوری، بلافاصله بعد از تایید پرداخت', 'link' => 'لینک دسترسی خودکار بعد از تایید پرداخت'][$dmode] ?? 'تحویل سریع پس از تایید پرداخت';
?>
<section class="container pd-wrap" style="--c:<?= e($p['color']) ?>">
    <nav class="breadcrumb" data-reveal>
        <a href="<?= e(url('')) ?>"><?= icon('home', 15) ?> خانه</a><span>/</span>
        <a href="<?= e(url('shop')) ?>"><?= e(setting('shop_title')) ?></a>
        <?php if ($p['cat_name']): ?><span>/</span><a href="<?= e(url('shop?cat=' . rawurlencode($p['cat_slug']))) ?>"><?= e($p['cat_name']) ?></a><?php endif; ?>
    </nav>

    <div class="pd-grid">
        <div class="pd-visual glass" data-reveal>
            <div class="pd-stage">
                <span class="pd-ring r1" aria-hidden="true"></span><span class="pd-ring r2" aria-hidden="true"></span>
                <?= product_image_html($p, 'pd-hero-img') ?>
                <?php if ($p['badge']): ?><span class="ps-badge"><?= e($p['badge']) ?></span><?php endif; ?>
            </div>
            <?php if ($gallery): ?>
            <div class="pd-thumbs" id="pdThumbs">
                <?php if (!empty($p['image'])): ?><button type="button" class="pd-th on" data-src="<?= e(media_url((string) $p['image'])) ?>" aria-label="تصویر ۱"><img src="<?= e(media_url((string) $p['image'])) ?>" alt="" loading="lazy"></button><?php endif; ?>
                <?php foreach ($gallery as $gi => $g): ?><button type="button" class="pd-th" data-src="<?= e(media_url($g)) ?>" aria-label="تصویر <?= fa_num((string) ($gi + 2)) ?>"><img src="<?= e(media_url($g)) ?>" alt="" loading="lazy"></button><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="pd-meta">
                <span class="ps-type"><?= e($type['icon']) ?> <?= e($p['cat_name'] ?: $type['name']) ?></span>
                <h1><?= e($p['title']) ?></h1>
                <?php if ($p['subtitle']): ?><p class="pd-sub"><?= e($p['subtitle']) ?></p><?php endif; ?>
                <div class="pd-trust">
                    <span><?= $dmode === 'ship' ? '🚚' : icon('bolt', 16) ?> <?= e($dText) ?></span>
                    <span><?= icon('shield', 16) ?> پرداخت کارت‌به‌کارت + بررسی فیش</span>
                    <?php if ((int) $p['sales'] > 0): ?><span><?= icon('fire', 16) ?> <?= fa_num((string) $p['sales']) ?> فروش موفق</span><?php endif; ?>
                </div>
            </div>
        </div>

        <form class="pd-buy glass" method="post" id="buyForm" data-pid="<?= (int) $p['id'] ?>" data-reveal novalidate>
            <?= csrf_field() ?>
            <?php if ($formMsg): ?><div class="alert alert-error"><?= e($formMsg) ?></div><?php endif; ?>

            <?php if ($variants): ?>
            <div class="pd-step"><b><span>۱</span> انتخاب بسته</b></div>
            <div class="pkg-list">
                <?php foreach ($variants as $v):
                    $price = variant_price($p, $v); $cmp = variant_compare($p, $v);
                    $out = $v['stock'] !== null && (int) $v['stock'] <= 0; ?>
                    <label class="pkg<?= $out ? ' is-out' : '' ?>">
                        <input type="radio" name="vid" value="<?= (int) $v['id'] ?>" data-price="<?= (int) $price ?>" <?= !$useCustom && $selVid === (int) $v['id'] ? 'checked' : '' ?> <?= $out || $price === null ? 'disabled' : '' ?>>
                        <span class="pkg-box">
                            <span class="pkg-radio" aria-hidden="true"></span>
                            <span class="pkg-text"><b><?= e($v['title']) ?></b><?php if ($v['subtitle']): ?><small><?= e($v['subtitle']) ?></small><?php endif; ?></span>
                            <?php if ($v['badge']): ?><em class="pkg-badge"><?= e($v['badge']) ?></em><?php endif; ?>
                            <span class="pkg-price">
                                <?php if ($out): ?><small>ناموجود</small>
                                <?php elseif ($price === null): ?><small>به‌زودی</small>
                                <?php else: ?>
                                    <?php if ($cmp): ?><span class="pkg-was"><i class="pkg-off"><?= fa_num((string) round(100 - $price * 100 / $cmp)) ?>٪ تخفیف</i><s><?= money($cmp) ?></s></span><?php endif; ?>
                                    <b><?= money($price) ?></b><small>تومان</small>
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
                <?php if ($custom['on']): ?>
                    <label class="pkg pkg-custom">
                        <input type="radio" name="vid" value="0" <?= $useCustom ? 'checked' : '' ?>>
                        <span class="pkg-box"><span class="pkg-radio" aria-hidden="true"></span><span class="pkg-text"><b>✨ مقدار دلخواه</b><small>هر تعداد <?= e($custom['unit']) ?> که می‌خوای</small></span></span>
                    </label>
                <?php endif; ?>
            </div>
            <?php elseif ($custom['on']): ?>
                <input type="hidden" name="vid" value="0">
            <?php endif; ?>

            <?php if ($custom['on'] && $cjs): ?>
            <div class="amount-box" id="amountBox" <?= $variants && !$useCustom ? 'hidden' : '' ?> data-cfg='<?= e(json_encode($cjs)) ?>'>
                <div class="pd-step"><b><span><?= $variants ? '۲' : '۱' ?></span> مقدار <?= e($custom['unit']) ?></b><small>از <?= fa_num($fmtNum($custom['min'])) ?> تا <?= fa_num($fmtNum($custom['max'])) ?></small></div>
                <div class="amt-input">
                    <button type="button" class="amt-btn" data-amt="-" aria-label="کمتر"><?= icon('minus', 18) ?></button>
                    <input type="text" inputmode="decimal" name="amount" id="amountIn" dir="ltr" value="<?= e((string) ($old['amount'] ?? $fmtNum($custom['presets'][0] ?? $custom['min']))) ?>" autocomplete="off">
                    <span class="amt-unit"><?= e($custom['unit']) ?></span>
                    <button type="button" class="amt-btn" data-amt="+" aria-label="بیشتر"><?= icon('plus', 18) ?></button>
                </div>
                <?php if ($custom['presets']): ?>
                <div class="amt-presets">
                    <?php foreach ($custom['presets'] as $pr): ?><button type="button" data-preset="<?= e($fmtNum($pr)) ?>"><?= fa_num($fmtNum($pr)) ?></button><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <small class="amt-err" id="amountErr"></small>
            </div>
            <?php elseif ($custom['on']): ?>
                <div class="alert alert-info small">قیمت لحظه‌ای این محصول هنوز در دسترس نیست؛ چند دقیقه دیگر دوباره سر بزنید.</div>
            <?php endif; ?>

            <?php if ($fields): ?>
            <div class="pd-step"><b><span><?= fa_num((string) (($variants ? 1 : 0) + ($custom['on'] ? 1 : 0) + 1)) ?></span> اطلاعات تحویل</b></div>
            <div class="pd-fields">
                <?php foreach ($fields as $f): $k = $f['key']; $val = $old['f'][$k] ?? ''; $er = $formErr[$k] ?? ''; $t = $f['type'] ?? 'text'; ?>
                    <label class="field<?= $er ? ' has-err' : '' ?>"><span><?= e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></span>
                        <?php if ($t === 'textarea'): ?>
                            <textarea name="f[<?= e($k) ?>]" rows="3" placeholder="<?= e($f['placeholder'] ?? '') ?>"<?= !empty($f['required']) ? ' required' : '' ?>><?= e($val) ?></textarea>
                        <?php elseif ($t === 'select'): ?>
                            <select name="f[<?= e($k) ?>]"<?= !empty($f['required']) ? ' required' : '' ?>><option value="">انتخاب کنید...</option>
                                <?php foreach (array_filter(array_map('trim', explode(',', (string) ($f['options'] ?? '')))) as $op): ?><option<?= $val === $op ? ' selected' : '' ?>><?= e($op) ?></option><?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input name="f[<?= e($k) ?>]" value="<?= e($val) ?>" placeholder="<?= e($f['placeholder'] ?? '') ?>"
                                <?= in_array($t, ['username', 'email', 'tel', 'number', 'text'], true) && $t !== 'text' ? 'dir="ltr"' : '' ?>
                                <?= $t === 'email' ? 'type="email"' : ($t === 'tel' ? 'type="tel"' : ($t === 'number' ? 'inputmode="decimal"' : '')) ?>
                                autocomplete="off"<?= !empty($f['required']) ? ' required' : '' ?>>
                        <?php endif; ?>
                        <em class="field-err" data-for="<?= e($k) ?>"><?= e($er) ?></em>
                        <?php if (!empty($f['hint'])): ?><small class="muted"><?= e($f['hint']) ?></small><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="pd-total">
                <?php if ($variants): ?>
                <div class="qty-wrap" <?= $useCustom ? 'hidden' : '' ?> id="qtyWrap">
                <div class="qty" id="qtyBox" data-min="<?= $qMin ?>" data-max="<?= $qMax ?>">
                    <button type="button" data-q="-" aria-label="کمتر"><?= icon('minus', 16) ?></button>
                    <input type="text" name="qty" id="qtyIn" value="<?= max($qMin, min($qMax, (int) ($old['qty'] ?? $qMin))) ?>" inputmode="numeric" aria-label="تعداد (<?= e($unit) ?>)">
                    <button type="button" data-q="+" aria-label="بیشتر"><?= icon('plus', 16) ?></button>
                </div>
                <small class="qty-unit"><?= e($unit) ?><?= $qMin > 1 ? ' · حداقل ' . fa_num((string) $qMin) : '' ?></small>
                </div>
                <?php endif; ?>
                <div class="pt-sum"><small>مبلغ قابل پرداخت</small><b><span id="totalOut">—</span> <small>تومان</small></b></div>
            </div>

            <?php if ($sold): ?>
                <button class="btn btn-ghost btn-block" type="button" disabled>ناموجود</button>
            <?php else: ?>
            <div class="pd-actions">
                <button class="btn btn-primary btn-lg" type="submit" name="go" value="cart" id="addCart"><?= icon('cart', 19) ?> افزودن به سبد</button>
                <button class="btn btn-ghost btn-lg" type="submit" name="go" value="checkout"><?= icon('bolt', 18) ?> خرید سریع</button>
            </div>
            <?php endif; ?>
            <p class="pd-note muted small"><?= icon('shield', 14) ?> پرداخت به‌صورت کارت‌به‌کارت؛ بعد از ثبت سفارش، شماره کارت و مبلغ نمایش داده می‌شود.</p>
        </form>
    </div>

    <?php if (trim(strip_tags((string) $p['description'])) !== ''): ?>
    <article class="pd-desc glass prose" data-reveal>
        <h2 class="sec-title"><span class="sec-icon"><?= icon('sparkle', 18) ?></span> توضیحات</h2>
        <?= sanitize_html((string) $p['description']) ?>
    </article>
    <?php endif; ?>

    <?php if ($specs): ?>
    <section class="pd-specs glass" data-reveal>
        <h2 class="sec-title"><span class="sec-icon">📋</span> مشخصات</h2>
        <dl class="spec-list">
            <?php foreach ($specs as [$sk, $sv]): ?><div class="spec-row"><dt><?= e($sk) ?></dt><dd><?= e($sv) ?></dd></div><?php endforeach; ?>
        </dl>
    </section>
    <?php endif; ?>

    <?php if ($related): ?>
    <section class="pd-related">
        <div class="sec-head" data-reveal><h2 class="sec-title"><span class="sec-icon"><?= icon('grid', 20) ?></span> محصولات دیگر</h2></div>
        <div class="shop-grid">
            <?php foreach (array_slice($related, 0, 4) as $i => $rp) partial('product-card', ['p' => $rp, 'i' => $i]); ?>
        </div>
    </section>
    <?php endif; ?>
</section>
<?php if ($gallery): ?>
<script>
(function () {
    var t = document.getElementById('pdThumbs'); if (!t) return;
    t.addEventListener('click', function (e) {
        var b = e.target.closest('.pd-th'); if (!b) return;
        var hero = document.querySelector('.pd-stage .pd-hero-img');
        if (hero && hero.tagName === 'IMG') { hero.removeAttribute('srcset'); hero.src = b.dataset.src; }
        else if (hero) { var im = document.createElement('img'); im.className = 'pd-hero-img'; im.src = b.dataset.src; im.alt = ''; hero.replaceWith(im); }
        [].forEach.call(t.querySelectorAll('.pd-th'), function (x) { x.classList.toggle('on', x === b); });
    });
})();
</script>
<?php endif; ?>
