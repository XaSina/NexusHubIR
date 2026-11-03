<?php
/** @var array $p @var int $i */
$types = product_types();
$from = product_from_price($p);
$sold = $p['status'] === 'soldout';
?>
<a class="pcard-shop glass<?= $sold ? ' is-sold' : '' ?>" href="<?= e(product_url($p)) ?>" data-reveal style="--d:<?= (int) ($i ?? 0) % 8 ?>;--c:<?= e($p['color']) ?>">
    <span class="ps-media">
        <?= product_image_html($p, 'ps-img') ?>
        <span class="ps-glow" aria-hidden="true"></span>
        <?php if ($p['badge']): ?><span class="ps-badge"><?= e($p['badge']) ?></span><?php endif; ?>
        <?php if ($sold): ?><span class="ps-sold">ناموجود</span><?php endif; ?>
    </span>
    <span class="ps-body">
        <span class="ps-type"><?= e(($types[$p['type']] ?? $types['other'])['icon']) ?> <?= e($p['cat_name'] ?? '' ?: ($types[$p['type']] ?? $types['other'])['name']) ?></span>
        <b class="ps-title"><?= e($p['title']) ?></b>
        <?php if ($p['subtitle']): ?><span class="ps-sub"><?= e($p['subtitle']) ?></span><?php endif; ?>
        <span class="ps-foot">
            <span class="ps-price"><?php if ($from !== null): ?><small>از</small> <b><?= money($from) ?></b> <small>تومان</small><?php else: ?><small>قیمت به‌زودی</small><?php endif; ?></span>
            <span class="ps-go"><?= icon('arrow-left', 18) ?></span>
        </span>
    </span>
</a>
