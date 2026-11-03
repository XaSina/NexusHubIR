<?php /** @var array $pageRow */ ?>
<section class="container page-hero">
    <div class="ph-inner glass" data-reveal>
        <span class="ph-icon">📄</span>
        <div><h1 class="ph-title"><?= e($pageRow['title']) ?></h1><p class="ph-sub">آخرین بروزرسانی: <?= jdate('j F Y', $pageRow['updated_at']) ?></p></div>
    </div>
</section>
<section class="container layout-2col">
    <div class="col-main">
        <div class="post-body glass" data-reveal><div class="prose"><?= render_content((string) $pageRow['content'], false) ?></div></div>
    </div>
    <?php partial('sidebar'); ?>
</section>
