<?php
/** @var array $products @var array $scats @var ?array $curCat @var string $q */
$r = shop_rates();
$member = current_member();
?>
<section class="container shop-top">
<div class="shop-hero glass">
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
        <?php try { $mkt = setting_on('shop_tickers_on') ? market_board(false) : ['items' => []]; } catch (Throwable $mx) { nx_log_error($mx); $mkt = ['items' => []]; } if (!empty($mkt['items'])): ?>
        <div class="mkt" id="mktBoard" aria-live="polite">
            <div class="mkt-head"><span class="mkt-live"><i></i> بازار لحظه‌ای</span><span class="mkt-meta"><small id="mktSrc"><?= e($mkt['src'] ?? '') ?></small><small class="mkt-at" id="mktAt"><?= $mkt['at'] ? 'به‌روزرسانی ' . e(jdate('H:i', $mkt['at'])) : '' ?></small></span><button type="button" class="mkt-rf" id="mktRefresh" title="به‌روزرسانی قیمت‌ها" aria-label="به‌روزرسانی قیمت‌ها"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></button></div>
            <div class="mkt-grid">
                <?php foreach ($mkt['items'] as $it): $c = $it['chg']; ?>
                <div class="mkt-it<?= $it['toman'] === null ? ' wait' : '' ?>" data-k="<?= e($it['k']) ?>">
                    <span class="mkt-ic"><?= e($it['icon']) ?></span>
                    <span class="mkt-nm"><b><?= e($it['name']) ?></b><small dir="ltr"><?= e($it['short']) ?><?php if ($it['usd'] !== null && !in_array($it['k'], ['usd', 'usdt'], true)): ?> · $<span class="mkt-usd"><?= e(fa_num(market_usd($it['usd']))) ?></span><?php endif; ?></small></span>
                    <span class="mkt-pr"><b class="mkt-t"><?= $it['toman'] ? money($it['toman']) : '…' ?></b><small>تومان</small></span>
                    <em class="mkt-ch <?= $c === null ? 'n' : ($c >= 0 ? 'up' : 'dn') ?>" dir="ltr"><?= $c === null ? '' : ($c >= 0 ? '▲ ' : '▼ ') . e(fa_num(number_format(abs($c), 2))) . '٪' ?></em>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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
