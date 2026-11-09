<?php
/** @var array $lines @var array $member @var string $err @var array $coupons */
$total = array_sum(array_map(fn($l) => (int) $l['total'], $lines));
$coupons = $coupons ?? [];
$best = 0; $bestOff = 0;
foreach ($coupons as $cc) if ($cc['off'] > $bestOff) { $bestOff = $cc['off']; $best = (int) $cc['c']['id']; }
$promo = promo_session(); $pc = null; $pWhy = '';
if ($promo) { $pWhy = promo_problem($promo, $member); $pc = promo_for_cart($promo, $lines); if ($pWhy === '' && $pc['why'] === '' && $pc['off'] >= $bestOff) { $bestOff = $pc['off']; $best = 'p'; } }
$bad = (bool) array_filter($lines, fn($l) => $l['error']);
$needShip = $needShip ?? false; $shipIn = $shipIn ?? []; $shipErr = $shipErr ?? [];
$methods = $needShip ? ship_methods() : [];
$quotes = []; foreach ($methods as $mi => $mm) $quotes[$mi] = ship_quote($lines, $mi);
$selM = isset($quotes[(int) ($shipIn['method'] ?? 0)]) ? (int) ($shipIn['method'] ?? 0) : 0;
$shipNow = $needShip ? $quotes[$selM]['cost'] : 0;
$sf = function (string $k, string $label, string $ph = '', string $extra = '') use ($shipIn, $shipErr) {
    return '<label class="field' . (isset($shipErr[$k]) ? ' has-err' : '') . '"><span>' . e($label) . '</span><input name="ship[' . e($k) . ']" value="' . e((string) ($shipIn[$k] ?? '')) . '" placeholder="' . e($ph) . '" ' . $extra . (str_contains($extra, 'data-optional') ? '' : ' required') . '>'
        . (isset($shipErr[$k]) ? '<em class="field-err">' . e($shipErr[$k]) . '</em>' : '') . '</label>';
};
?>
<section class="container shop-page">
    <?php partial('shop-steps', ['at' => 2]); ?>
    <div class="sp-head" data-reveal><h1><?= icon('shield', 26) ?> تکمیل خرید</h1><a class="btn btn-ghost btn-sm" href="<?= e(url('cart')) ?>"><?= icon('arrow-right', 16) ?> بازگشت به سبد</a></div>
    <?php if ($err): ?><div class="alert alert-error" data-reveal><?= e($err) ?></div><?php endif; ?>

    <form method="post" class="cart-grid" id="checkoutForm">
        <?= csrf_field() ?>
        <div class="co-main">
            <div class="co-card glass" data-reveal>
                <h3><?= icon('user', 18) ?> اطلاعات خریدار</h3>
                <div class="co-buyer">
                    <span><small>نام</small><b><?= e(member_full_name($member)) ?></b></span>
                    <span><small>موبایل</small><b dir="ltr"><?= e(fa_num($member['phone'])) ?></b></span>
                    <span><small>کد عضویت</small><b dir="ltr"><?= e($member['member_code']) ?></b></span>
                </div>
            </div>
            <div class="co-card glass" data-reveal>
                <h3><?= icon('box', 18) ?> اقلام سفارش</h3>
                <?php foreach ($lines as $l): $p = $l['product']; ?>
                    <div class="co-line">
                        <span class="co-ic" style="--c:<?= e($p['color']) ?>"><?= e($p['icon']) ?></span>
                        <span class="co-t"><b><?= e($l['title']) ?></b><small><?= e($l['sub']) ?><?= $l['qty'] > 1 ? ' × ' . fa_num((string) $l['qty']) : '' ?></small>
                            <?php foreach (order_field_pairs($p, $l['fields']) as [$lab, $val]): ?><small class="co-f"><?= e($lab) ?>: <b dir="auto"><?= e($val) ?></b></small><?php endforeach; ?>
                            <?php if ($l['error']): ?><small class="cl-err">⚠️ <?= e($l['error']) ?></small><?php endif; ?>
                        </span>
                        <b class="co-p"><?= money($l['total']) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($needShip): ?>
            <div class="co-card co-ship glass" data-reveal>
                <h3>🚚 آدرس و روش ارسال</h3>
                <div class="co-ship-grid">
                    <?= $sf('name', 'نام و نام خانوادگی گیرنده', '', 'autocomplete="name"') ?>
                    <?= $sf('phone', 'شماره موبایل گیرنده', '09xxxxxxxxx', 'dir="ltr" inputmode="tel" autocomplete="tel"') ?>
                    <?= $sf('province', 'استان', 'مثلاً اصفهان', 'autocomplete="address-level1"') ?>
                    <?= $sf('city', 'شهر', 'مثلاً اصفهان', 'autocomplete="address-level2"') ?>
                </div>
                <label class="field<?= isset($shipErr['address']) ? ' has-err' : '' ?>"><span>آدرس کامل</span><textarea name="ship[address]" rows="2" required placeholder="خیابان، کوچه، پلاک، واحد" autocomplete="street-address"><?= e((string) ($shipIn['address'] ?? '')) ?></textarea><?= isset($shipErr['address']) ? '<em class="field-err">' . e($shipErr['address']) . '</em>' : '' ?></label>
                <div class="co-ship-grid"><?= $sf('postcode', 'کد پستی' . (setting_on('ship_postcode_req') ? '' : ' (اختیاری)'), '۱۰ رقم', 'dir="ltr" inputmode="numeric" autocomplete="postal-code"' . (setting_on('ship_postcode_req') ? '' : ' data-optional')) ?></div>
                <div class="co-ship-methods">
                    <?php foreach ($methods as $mi => $mm): $qq = $quotes[$mi]; ?>
                    <label class="co-sm"><input type="radio" name="ship[method]" value="<?= $mi ?>" data-cost="<?= (int) $qq['cost'] ?>" <?= $mi === $selM ? 'checked' : '' ?>>
                        <span class="co-sm-t"><b><?= e($mm['name']) ?></b><?php if ($mm['days'] !== ''): ?><small><?= e($mm['days']) ?></small><?php endif; ?></span>
                        <b class="co-sm-p"><?= $qq['cost'] ? money((int) $qq['cost']) . ' <small>تومان</small>' : 'رایگان' ?></b></label>
                    <?php endforeach; ?>
                </div>
                <?php if ((int) setting('ship_free_over') > 0): ?><p class="co-ship-note">🎉 خرید بالای <?= money((int) setting('ship_free_over')) ?> تومان، ارسال رایگان<?= $quotes && $quotes[0]['extra'] ? ' (به‌جز هزینه‌ی ویژه‌ی بعضی کالاها)' : '' ?></p><?php endif; ?>
                <?php if ($n = trim((string) setting('ship_note'))): ?><p class="co-ship-note"><?= e($n) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="co-card glass" data-reveal>
                <label class="field"><span>توضیحات سفارش (اختیاری)</span><textarea name="note" rows="3" maxlength="1000" placeholder="اگر نکته‌ای درباره سفارش دارید اینجا بنویسید..."></textarea></label>
            </div>
        </div>
        <aside class="cart-sum glass" data-reveal>
            <h3>پرداخت</h3>
            <div class="pay-method on"><span class="pm-ic"><?= icon('card', 22) ?></span><span><b>کارت‌به‌کارت</b><small>واریز به کارت و ارسال تصویر فیش</small></span><i class="pm-check">✓</i></div>
            <?php if ($coupons || $promo): ?>
            <div class="co-coupons">
                <small class="co-cp-title">🏷️ کدهای تخفیف جایزه‌ی تو</small>
                <label class="co-cp"><input type="radio" name="coupon" value="0" data-off="0" <?= $best ? '' : 'checked' ?>><span><b>بدون تخفیف</b></span></label>
                <?php if ($promo): $pBad = $pWhy !== '' ? $pWhy : $pc['why']; ?>
                <label class="co-cp<?= $pBad ? ' is-off' : '' ?>"><input type="radio" name="coupon" value="p:<?= (int) $promo['id'] ?>" data-off="<?= $pBad ? 0 : (int) $pc['off'] ?>" <?= $pBad ? 'disabled' : '' ?> <?= $best === 'p' ? 'checked' : '' ?>>
                    <span><b><?= e(discount_text(promo_disc($promo))) ?></b><small><?= $pBad ? '⚠️ ' . e($pBad) : '− ' . money((int) $pc['off']) . ' تومان' ?></small></span><code dir="ltr"><?= e($promo['code']) ?></code></label>
                <?php endif; ?>
                <?php foreach ($coupons as $cc): $c = $cc['c']; ?>
                <label class="co-cp<?= $cc['why'] ? ' is-off' : '' ?>"><input type="radio" name="coupon" value="<?= (int) $c['id'] ?>" data-off="<?= (int) $cc['off'] ?>" <?= $cc['why'] ? 'disabled' : '' ?> <?= $best !== 'p' && (int) $c['id'] === (int) $best ? 'checked' : '' ?>>
                    <span><b><?= e($cc['text']) ?></b><small><?= $cc['why'] ? '⚠️ ' . e($cc['why']) : '− ' . money($cc['off']) . ' تومان' ?><?= $c['expires_at'] ? ' · تا ' . e(jdate('j F', strtotime($c['expires_at']))) : '' ?></small></span><code dir="ltr"><?= e($c['code']) ?></code></label>
                <?php endforeach; ?>
            </div>
            <div class="cs-row"><span>جمع سبد</span><b><?= money($total) ?> <small>تومان</small></b></div>
            <div class="cs-row cs-disc" id="coDiscRow"<?= $bestOff ? '' : ' hidden' ?>><span>تخفیف جایزه</span><b id="coDisc">− <?= money($bestOff) ?> <small>تومان</small></b></div>
            <?php endif; ?>
            <?php if ($needShip): ?><div class="cs-row"><span>🚚 هزینه‌ی ارسال</span><b id="coShip"><?= $shipNow ? money($shipNow) . ' <small>تومان</small>' : 'رایگان' ?></b></div><?php endif; ?>
            <div class="cs-row cs-total"><span>مبلغ قابل پرداخت</span><b><span id="coPay" data-total="<?= $total ?>" data-ship="<?= (int) $shipNow ?>"><?= money($total - min($bestOff, $total - 1) + $shipNow) ?></span> <small>تومان</small></b></div>
            <?php if ($n = trim((string) setting('shop_notice'))): ?><p class="co-notice"><?= icon('clock', 15) ?> <?= e($n) ?></p><?php endif; ?>
            <?php if ($terms = trim((string) setting('shop_terms'))): ?><details class="co-terms"><summary><?= icon('shield', 15) ?> قوانین فروشگاه</summary><div><?= nl2br(e($terms)) ?></div></details><?php endif; ?>
            <label class="co-agree"><input type="checkbox" name="agree" value="1" required> <span>اطلاعات سفارش را بررسی کردم و قوانین فروشگاه را می‌پذیرم.</span></label>
            <button class="btn btn-primary btn-lg btn-block" type="submit"<?= $bad ? ' disabled' : '' ?>><?= icon('check', 19) ?> ثبت سفارش و دریافت شماره کارت</button>
            <p class="muted small">مهلت پرداخت پس از ثبت: <?= fa_num((string) (int) setting('shop_pay_minutes', '60')) ?> دقیقه</p>
        </aside>
    </form>
</section>
<script>
(function () {
    var pay = document.getElementById('coPay');
    var fa = function (n) { return String(Math.round(n).toLocaleString('en-US')).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }).replace(/,/g, '٬'); };
    function paint() {
        var total = +pay.dataset.total, c = document.querySelector('input[name=coupon]:checked'), off = c ? (+c.dataset.off || 0) : 0;
        off = Math.min(off, total - 1);
        var m = document.querySelector('input[name="ship[method]"]:checked'), ship = m ? (+m.dataset.cost || 0) : 0;
        pay.textContent = fa(total - off + ship);
        var row = document.getElementById('coDiscRow'); if (row) { row.hidden = !off; document.getElementById('coDisc').innerHTML = '− ' + fa(off) + ' <small>تومان</small>'; }
        var sh = document.getElementById('coShip'); if (sh) sh.innerHTML = ship ? fa(ship) + ' <small>تومان</small>' : 'رایگان';
    }
    document.addEventListener('change', function (e) { if (e.target.name === 'coupon' || e.target.name === 'ship[method]') paint(); });
})();
</script>
