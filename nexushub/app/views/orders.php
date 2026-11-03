<?php
/** @var array $orders @var array $member */
$S = order_statuses();
?>
<section class="container shop-page">
    <div class="sp-head" data-reveal>
        <h1><?= icon('box', 26) ?> سفارش‌های من</h1>
        <div class="sp-head-act">
            <?php if (setting_on('shop_support_on')): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('support')) ?>"><?= icon('support', 16) ?> پشتیبانی</a><?php endif; ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('shop')) ?>"><?= icon('bag', 16) ?> فروشگاه</a>
        </div>
    </div>
    <?php if (!$orders): ?>
        <div class="shop-empty glass" data-reveal><span>📦</span><b>هنوز سفارشی ثبت نکرده‌ای</b><p class="muted">اولین خریدت رو از فروشگاه NEXUS انجام بده.</p><a class="btn btn-primary" href="<?= e(url('shop')) ?>"><?= icon('bag', 18) ?> رفتن به فروشگاه</a></div>
    <?php else: ?>
    <div class="olist">
        <?php foreach ($orders as $i => $o): $its = order_items((int) $o['id']); $s = $S[$o['status']] ?? ['name' => $o['status'], 'icon' => '', 'tone' => 'info']; ?>
            <a class="orow glass" href="<?= e(order_url($o)) ?>" data-reveal style="--d:<?= $i % 8 ?>">
                <span class="or-ic"><?= e($its[0]['icon'] ?? '🛍️') ?></span>
                <span class="or-main"><b><?= e(implode('، ', array_map(fn($x) => $x['title'], $its))) ?></b><small dir="ltr"><?= e($o['code']) ?></small><small><?= jdate('j F Y — H:i', $o['created_at']) ?></small></span>
                <span class="or-status tone-<?= e($s['tone']) ?>"><?= e($s['icon']) ?> <?= e($s['name']) ?></span>
                <b class="or-total"><?= money((int) $o['total']) ?> <small>تومان</small></b>
                <span class="ps-go"><?= icon('arrow-left', 18) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
