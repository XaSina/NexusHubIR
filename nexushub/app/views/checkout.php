<?php
/** @var array $lines @var array $member @var string $err @var array $coupons */
$total = array_sum(array_map(fn($l) => (int) $l['total'], $lines));
$coupons = $coupons ?? [];
$best = 0; $bestOff = 0;
foreach ($coupons as $cc) if ($cc['off'] > $bestOff) { $bestOff = $cc['off']; $best = (int) $cc['c']['id']; }
$bad = (bool) array_filter($lines, fn($l) => $l['error']);
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
            <div class="co-card glass" data-reveal>
                <label class="field"><span>توضیحات سفارش (اختیاری)</span><textarea name="note" rows="3" maxlength="1000" placeholder="اگر نکته‌ای درباره سفارش دارید اینجا بنویسید..."></textarea></label>
            </div>
        </div>
        <aside class="cart-sum glass" data-reveal>
            <h3>پرداخت</h3>
            <div class="pay-method on"><span class="pm-ic"><?= icon('card', 22) ?></span><span><b>کارت‌به‌کارت</b><small>واریز به کارت و ارسال تصویر فیش</small></span><i class="pm-check">✓</i></div>
            <?php if ($coupons): ?>
            <div class="co-coupons">
                <small class="co-cp-title">🏷️ کدهای تخفیف جایزه‌ی تو</small>
                <label class="co-cp"><input type="radio" name="coupon" value="0" data-off="0" <?= $best ? '' : 'checked' ?>><span><b>بدون تخفیف</b></span></label>
                <?php foreach ($coupons as $cc): $c = $cc['c']; ?>
                <label class="co-cp<?= $cc['why'] ? ' is-off' : '' ?>"><input type="radio" name="coupon" value="<?= (int) $c['id'] ?>" data-off="<?= (int) $cc['off'] ?>" <?= $cc['why'] ? 'disabled' : '' ?> <?= (int) $c['id'] === $best ? 'checked' : '' ?>>
                    <span><b><?= e($cc['text']) ?></b><small><?= $cc['why'] ? '⚠️ ' . e($cc['why']) : '− ' . money($cc['off']) . ' تومان' ?><?= $c['expires_at'] ? ' · تا ' . e(jdate('j F', strtotime($c['expires_at']))) : '' ?></small></span><code dir="ltr"><?= e($c['code']) ?></code></label>
                <?php endforeach; ?>
            </div>
            <div class="cs-row"><span>جمع سبد</span><b><?= money($total) ?> <small>تومان</small></b></div>
            <div class="cs-row cs-disc" id="coDiscRow"<?= $bestOff ? '' : ' hidden' ?>><span>تخفیف جایزه</span><b id="coDisc">− <?= money($bestOff) ?> <small>تومان</small></b></div>
            <?php endif; ?>
            <div class="cs-row cs-total"><span>مبلغ قابل پرداخت</span><b><span id="coPay" data-total="<?= $total ?>"><?= money($total - min($bestOff, $total - 1)) ?></span> <small>تومان</small></b></div>
            <?php if ($n = trim((string) setting('shop_notice'))): ?><p class="co-notice"><?= icon('clock', 15) ?> <?= e($n) ?></p><?php endif; ?>
            <?php if ($terms = trim((string) setting('shop_terms'))): ?><details class="co-terms"><summary><?= icon('shield', 15) ?> قوانین فروشگاه</summary><div><?= nl2br(e($terms)) ?></div></details><?php endif; ?>
            <label class="co-agree"><input type="checkbox" name="agree" value="1" required> <span>اطلاعات سفارش را بررسی کردم و قوانین فروشگاه را می‌پذیرم.</span></label>
            <button class="btn btn-primary btn-lg btn-block" type="submit"<?= $bad ? ' disabled' : '' ?>><?= icon('check', 19) ?> ثبت سفارش و دریافت شماره کارت</button>
            <p class="muted small">مهلت پرداخت پس از ثبت: <?= fa_num((string) (int) setting('shop_pay_minutes', '60')) ?> دقیقه</p>
        </aside>
    </form>
</section>
<script>
document.addEventListener('change', function (e) {
    if (e.target.name !== 'coupon') return;
    var off = +e.target.dataset.off || 0, pay = document.getElementById('coPay'), total = +pay.dataset.total, fa = function (n) { return String(Math.round(n).toLocaleString('en-US')).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }).replace(/,/g, '٬'); };
    off = Math.min(off, total - 1);
    pay.textContent = fa(total - off);
    var row = document.getElementById('coDiscRow'); if (row) { row.hidden = !off; document.getElementById('coDisc').innerHTML = '− ' + fa(off) + ' <small>تومان</small>'; }
});
</script>
