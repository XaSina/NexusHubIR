<?php
/** @var array $lines @var array $member @var string $err */
$total = array_sum(array_map(fn($l) => (int) $l['total'], $lines));
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
            <div class="cs-row cs-total"><span>مبلغ قابل پرداخت</span><b><?= money($total) ?> <small>تومان</small></b></div>
            <?php if ($n = trim((string) setting('shop_notice'))): ?><p class="co-notice"><?= icon('clock', 15) ?> <?= e($n) ?></p><?php endif; ?>
            <?php if ($terms = trim((string) setting('shop_terms'))): ?><details class="co-terms"><summary><?= icon('shield', 15) ?> قوانین فروشگاه</summary><div><?= nl2br(e($terms)) ?></div></details><?php endif; ?>
            <label class="co-agree"><input type="checkbox" name="agree" value="1" required> <span>اطلاعات سفارش را بررسی کردم و قوانین فروشگاه را می‌پذیرم.</span></label>
            <button class="btn btn-primary btn-lg btn-block" type="submit"<?= $bad ? ' disabled' : '' ?>><?= icon('check', 19) ?> ثبت سفارش و دریافت شماره کارت</button>
            <p class="muted small">مهلت پرداخت پس از ثبت: <?= fa_num((string) (int) setting('shop_pay_minutes', '60')) ?> دقیقه</p>
        </aside>
    </form>
</section>
