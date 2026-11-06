<?php
/** @var int $at  1 cart · 2 checkout · 3 payment · 4 review · 5 delivered — same look as the order page tracker */
$steps = [['🛒', 'سبد خرید', 'انتخاب محصول'], ['📝', 'تکمیل خرید', 'تأیید اطلاعات'], ['💳', 'پرداخت', 'کارت‌به‌کارت'], ['🔎', 'بررسی فیش', ''], ['🎁', 'تحویل', '']];
?>
<ol class="order-steps flow-tracker stage-<?= (int) $at ?>" style="--p:<?= ($at - 1) / (count($steps) - 1) ?>" data-reveal>
    <?php foreach ($steps as $i => [$ic, $name, $sub]): $n = $i + 1; ?>
        <li class="<?= $n < $at ? 'done' : ($n === $at ? 'now' : '') ?>" style="--i:<?= $i ?>">
            <span class="os-ic"><i class="os-emo"><?= $ic ?></i><svg class="os-check" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7"/></svg><i class="os-pulse" aria-hidden="true"></i></span>
            <b><?= e($name) ?></b><?php if ($sub): ?><small><?= e($sub) ?></small><?php endif; ?>
        </li>
    <?php endforeach; ?>
    <span class="os-beam" aria-hidden="true"></span>
</ol>
