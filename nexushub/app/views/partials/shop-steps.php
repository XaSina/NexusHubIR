<?php
/** @var int $at  1 cart · 2 checkout · 3 payment · 4 review · 5 delivered */
$steps = [['🛒', 'سبد خرید'], ['📝', 'تکمیل خرید'], ['💳', 'پرداخت'], ['🔎', 'بررسی فیش'], ['🎁', 'تحویل']];
?>
<ol class="flow-steps" data-reveal style="--p:<?= ($at - 1) / (count($steps) - 1) ?>">
    <?php foreach ($steps as $i => [$ic, $name]): $n = $i + 1; ?>
        <li class="<?= $n < $at ? 'done' : ($n === $at ? 'now' : '') ?>"><span><?= $n < $at ? '✓' : $ic ?></span><b><?= e($name) ?></b></li>
    <?php endforeach; ?>
</ol>
