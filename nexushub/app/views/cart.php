<?php
/** @var array $lines */
$total = 0; $bad = false;
foreach ($lines as $l) { if ($l['error'] || $l['total'] === null) $bad = true; else $total += $l['total']; }
?>
<section class="container shop-page">
    <?php partial('shop-steps', ['at' => 1]); ?>
    <div class="sp-head" data-reveal>
        <h1><?= icon('cart', 26) ?> سبد خرید</h1>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('shop')) ?>"><?= icon('arrow-right', 16) ?> ادامه خرید</a>
    </div>

    <?php if (!$lines): ?>
        <div class="shop-empty glass" data-reveal><span class="se-cart">🛒</span><b>سبد خرید خالیه!</b><p class="muted">یه سر به فروشگاه بزن و محصول مورد نظرت رو انتخاب کن.</p><a class="btn btn-primary" href="<?= e(url('shop')) ?>"><?= icon('bag', 18) ?> رفتن به فروشگاه</a></div>
    <?php else: ?>
    <div class="cart-grid">
        <div class="cart-lines">
            <?php foreach ($lines as $i => $l): $p = $l['product']; ?>
            <div class="cline glass<?= $l['error'] ? ' has-err' : '' ?>" data-reveal style="--d:<?= $i ?>;--c:<?= e($p['color']) ?>">
                <a class="cl-media" href="<?= e(product_url($p)) ?>"><?= product_image_html($p, 'cl-img') ?></a>
                <div class="cl-body">
                    <a class="cl-title" href="<?= e(product_url($p)) ?>"><?= e($l['title']) ?></a>
                    <span class="cl-sub"><?= e($l['sub']) ?></span>
                    <?php foreach (order_field_pairs($p, $l['fields']) as [$lab, $val]): ?><span class="cl-field"><?= e($lab) ?>: <b dir="auto"><?= e($val) ?></b></span><?php endforeach; ?>
                    <?php if ($l['error']): ?><span class="cl-err">⚠️ <?= e($l['error']) ?></span><?php endif; ?>
                </div>
                <div class="cl-side">
                    <b class="cl-price"><?= money($l['total']) ?> <small>تومان</small></b>
                    <div class="cl-actions">
                        <?php if ($l['variant']): ?>
                        <form method="post" class="qty qty-sm" data-autosubmit>
                            <?= csrf_field() ?><input type="hidden" name="act" value="qty"><input type="hidden" name="key" value="<?= e($l['key']) ?>">
                            <button type="button" data-q="-" aria-label="کمتر"><?= icon('minus', 14) ?></button>
                            <input type="text" name="qty" value="<?= (int) $l['qty'] ?>" inputmode="numeric" aria-label="تعداد">
                            <button type="button" data-q="+" aria-label="بیشتر"><?= icon('plus', 14) ?></button>
                        </form>
                        <?php endif; ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="remove"><input type="hidden" name="key" value="<?= e($l['key']) ?>">
                            <button class="icon-btn sm cl-rm" type="submit" aria-label="حذف" title="حذف"><?= icon('trash', 16) ?></button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <aside class="cart-sum glass" data-reveal>
            <h3>خلاصه سفارش</h3>
            <div class="cs-row"><span>تعداد اقلام</span><b><?= fa_num((string) cart_count()) ?></b></div>
            <div class="cs-row cs-total"><span>مبلغ کل</span><b><?= money($total) ?> <small>تومان</small></b></div>
            <?php if ($bad): ?><div class="alert alert-error small">برخی اقلام مشکل دارند؛ آن‌ها را حذف یا اصلاح کنید.</div><?php endif; ?>
            <a class="btn btn-primary btn-lg btn-block<?= $bad ? ' is-disabled' : '' ?>" href="<?= $bad ? '#' : e(url('checkout')) ?>"><?= icon('shield', 18) ?> ادامه و پرداخت</a>
            <form method="post" class="cs-clear"><?= csrf_field() ?><input type="hidden" name="act" value="clear"><button type="submit" class="link-btn">خالی کردن سبد</button></form>
            <?php if (!current_member()): ?><p class="muted small"><?= icon('user', 14) ?> برای ثبت سفارش، وارد حساب کاربری شوید یا ثبت‌نام کنید.</p><?php endif; ?>
        </aside>
    </div>
    <?php endif; ?>
</section>
