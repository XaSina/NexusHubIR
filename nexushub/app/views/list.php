<?php
/** @var array $posts @var array $pg @var string $heading @var string $base */
$color = $color ?? null;
?>
<section class="container page-hero" <?= $color ? 'style="--c:' . e($color) . '"' : '' ?>>
    <div class="ph-inner glass" data-reveal>
        <span class="ph-icon"><?= e($icon ?? '📰') ?></span>
        <div>
            <h1 class="ph-title"><?= e($heading) ?></h1>
            <?php if (!empty($sub)): ?><p class="ph-sub"><?= e($sub) ?></p><?php endif; ?>
        </div>
        <?php if (isset($search)): ?>
            <form class="ph-search" action="<?= e(url('search')) ?>" method="get">
                <?= icon('search', 18) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="جستجو در اخبار..." minlength="2" required>
                <button class="btn btn-primary btn-sm" type="submit">جستجو</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<section class="container layout-2col">
    <div class="col-main">
        <?php if ($posts): ?>
            <div class="grid-cards">
                <?php foreach ($posts as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
            </div>
            <?= pagination_html($pg, $base) ?>
        <?php else: ?>
            <div class="empty glass" data-reveal><span>🛸</span><p>هیچ خبری پیدا نشد.</p><a class="btn btn-ghost" href="<?= e(url('')) ?>">بازگشت به خانه</a></div>
        <?php endif; ?>
    </div>
    <?php partial('sidebar'); ?>
</section>
