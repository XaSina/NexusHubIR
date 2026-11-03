<?php
/** @var array $products @var array $scats @var ?array $curCat @var string $q */
$r = shop_rates();
$member = current_member();
?>
<section class="container shop-hero glass">
    <div class="sh-orbs" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="sh-grid" aria-hidden="true"></div>
    <div class="sh-text">
        <span class="eyebrow"><?= icon('bag', 15) ?> <?= e(setting('site_title')) ?> STORE</span>
        <h1><?= e(setting('shop_title')) ?></h1>
        <p><?= e(setting('shop_tagline')) ?></p>
        <form class="sh-search" action="<?= e(url('shop')) ?>" method="get">
            <?= icon('search', 18) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="جستجو در محصولات..." aria-label="جستجو در محصولات">
            <?php if ($curCat): ?><input type="hidden" name="cat" value="<?= e($curCat['slug']) ?>"><?php endif; ?>
            <button class="btn btn-primary btn-sm" type="submit">جستجو</button>
        </form>
        <div class="sh-links">
            <?php if ($member): ?><a href="<?= e(url('orders')) ?>"><?= icon('box', 16) ?> سفارش‌های من</a><?php endif; ?>
            <?php if (setting_on('shop_support_on')): ?><a href="<?= e(url('support')) ?>"><?= icon('support', 16) ?> پشتیبانی</a><?php endif; ?>
            <a href="<?= e(url('cart')) ?>"><?= icon('cart', 16) ?> سبد خرید<?php if ($cc = cart_count()): ?> <em><?= fa_num((string) $cc) ?></em><?php endif; ?></a>
        </div>
    </div>
    <div class="sh-side">
        <?php if ($r['ton']): ?>
        <div class="rate-chip" title="قیمت لحظه‌ای تون — منبع: <?= e($r['ton_src']) ?>">
            <span class="rc-ic">💎</span>
            <span><small>قیمت لحظه‌ای TON</small><b dir="ltr">$<?= e(fa_num(number_format($r['ton'], 2))) ?></b><?php if ($r['usd']): ?><em><?= money((int) round($r['ton'] * $r['usd'])) ?> تومان</em><?php endif; ?></span>
            <i class="rc-live"></i>
        </div>
        <?php endif; ?>
        <div class="sh-trust">
            <span><?= icon('bolt', 17) ?> تحویل سریع</span>
            <span><?= icon('shield', 17) ?> پرداخت امن</span>
            <span><?= icon('support', 17) ?> پشتیبانی واقعی</span>
        </div>
    </div>
</section>

<?php if ($scats): ?>
<nav class="container shop-cats h-scroll" aria-label="دسته‌های فروشگاه">
    <a href="<?= e(url('shop')) ?>" class="<?= !$curCat ? 'active' : '' ?>">✨ همه</a>
    <?php foreach ($scats as $c): ?>
        <a href="<?= e(url('shop?cat=' . rawurlencode($c['slug']))) ?>" class="<?= $curCat && $curCat['id'] == $c['id'] ? 'active' : '' ?>" style="--c:<?= e($c['color']) ?>"><?= e($c['icon']) ?> <?= e($c['name']) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="container shop-list">
    <?php if ($q !== ''): ?><p class="muted shop-q">نتایج جستجو برای «<?= e($q) ?>»: <?= fa_num((string) count($products)) ?> محصول</p><?php endif; ?>
    <?php if (!$products): ?>
        <div class="shop-empty glass"><span>🛍️</span><b><?= $q !== '' || $curCat ? 'محصولی پیدا نشد' : 'به‌زودی محصولات اضافه می‌شوند' ?></b><p class="muted">برای خبرهای فروشگاه، کانال تلگرام را دنبال کن.</p></div>
    <?php else: ?>
        <div class="shop-grid">
            <?php foreach ($products as $i => $p) partial('product-card', ['p' => $p, 'i' => $i]); ?>
        </div>
    <?php endif; ?>
</section>

<section class="container shop-steps">
    <div class="sec-head" data-reveal><h2 class="sec-title"><span class="sec-icon"><?= icon('sparkle', 20) ?></span> خرید در ۴ قدم</h2></div>
    <div class="ss-grid">
        <div class="ss glass" data-reveal style="--d:0"><span>۱</span><b>انتخاب محصول</b><small>بسته یا مقدار دلخواهت رو انتخاب کن</small></div>
        <div class="ss glass" data-reveal style="--d:1"><span>۲</span><b>کارت‌به‌کارت</b><small>مبلغ رو واریز کن و فیش رو بفرست</small></div>
        <div class="ss glass" data-reveal style="--d:2"><span>۳</span><b>بررسی فیش</b><small>پشتیبانی پرداختت رو تایید می‌کنه</small></div>
        <div class="ss glass" data-reveal style="--d:3"><span>۴</span><b>تحویل سفارش</b><small>نتیجه همون لحظه برات اعلان میشه</small></div>
    </div>
</section>
