<?php
/** @var array $p @var string $variant @var int $i @var int|null $rank */
$variant = $variant ?? 'card';
$i = $i ?? 0;
$href = post_url($p);
$catChip = !empty($p['cat_name']) ? '<a class="chip" href="' . e(url('category/' . rawurlencode($p['cat_slug']))) . '" style="--c:' . e($p['cat_color']) . '">' . e($p['cat_icon']) . ' ' . e($p['cat_name']) . '</a>' : '';
$excerpt = trim(strip_shortcodes((string) $p['excerpt'])) ?: str_excerpt((string) $p['content'], 140);
?>
<?php if ($variant === 'big'): ?>
<article class="pcard pcard-big" data-reveal style="--d:<?= $i ?>">
    <a class="pc-media" href="<?= e($href) ?>" tabindex="-1"><?= cover_html($p, '', $i === 0) ?></a>
    <div class="pc-overlay">
        <div class="pc-chips"><?= $catChip ?>
        <?php if (!empty($p['is_featured'])): ?><span class="chip chip-feat">⭐ ویژه</span><?php endif; ?>
        <?php if (!empty($p['is_breaking'])): ?><span class="chip chip-hot"><?= icon('bolt', 13) ?> فوری</span><?php endif; ?></div>
        <h3 class="pc-title"><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <p class="pc-excerpt"><?= e($excerpt) ?></p>
        <div class="pc-meta"><span><?= icon('clock', 14) ?> <?= jago($p['published_at']) ?></span><span><?= icon('eye', 14) ?> <?= fa_short_num((int) $p['views']) ?></span></div>
    </div>
</article>

<?php elseif ($variant === 'row'): ?>
<article class="pcard pcard-row" data-reveal style="--d:<?= $i ?>">
    <a class="pc-media" href="<?= e($href) ?>" tabindex="-1"><?= cover_html($p) ?></a>
    <div class="pc-body">
        <?= $catChip ?>
        <h3 class="pc-title"><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <div class="pc-meta"><span><?= icon('clock', 13) ?> <?= jago($p['published_at']) ?></span><span><?= icon('eye', 13) ?> <?= fa_short_num((int) $p['views']) ?></span></div>
    </div>
</article>

<?php elseif ($variant === 'rank'): ?>
<article class="pcard pcard-rank" data-reveal style="--d:<?= $i ?>">
    <span class="rank-num"><?= fa_num((string) ($rank ?? ($i + 1))) ?></span>
    <div class="pc-body">
        <h3 class="pc-title"><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <div class="pc-meta"><span><?= icon('eye', 13) ?> <?= fa_short_num((int) $p['views']) ?> بازدید</span><span><?= jago($p['published_at']) ?></span></div>
    </div>
</article>

<?php else: ?>
<article class="pcard pcard-card glass tilt" data-reveal style="--d:<?= $i % 6 ?>">
    <a class="pc-media" href="<?= e($href) ?>" tabindex="-1">
        <?= cover_html($p) ?>
        <?php if (!empty($p['is_breaking'])): ?><span class="chip chip-hot pc-float"><?= icon('bolt', 13) ?> فوری</span><?php endif; ?>
    </a>
    <div class="pc-body">
        <div class="pc-top"><?= $catChip ?><span class="pc-time"><?= jago($p['published_at']) ?></span></div>
        <h3 class="pc-title"><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <p class="pc-excerpt"><?= e($excerpt) ?></p>
        <div class="pc-meta">
            <span><?= icon('calendar', 14) ?> <?= jdate('j F', $p['published_at']) ?></span>
            <span><?= icon('eye', 14) ?> <?= fa_short_num((int) $p['views']) ?></span>
            <a class="pc-more" href="<?= e($href) ?>" aria-label="ادامه">ادامه <?= icon('arrow-left', 15) ?></a>
        </div>
    </div>
    <span class="card-shine" aria-hidden="true"></span>
</article>
<?php endif; ?>
