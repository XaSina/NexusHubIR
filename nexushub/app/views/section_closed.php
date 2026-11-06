<?php
/** @var string $what @var string $icon */
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container rf-page">
    <div class="rf-lockpage glass rf-in">
        <span class="rf-lock-ic"><?= e($icon) ?></span>
        <h1>بخش «<?= e($what) ?>» فعلاً بسته است</h1>
        <p class="muted">این بخش موقتاً غیرفعال شده. به زودی با یه برنامه‌ی جدید برمی‌گردیم — کانال تلگرام رو دنبال کن.</p>
        <a class="btn btn-ghost" href="<?= e(url('')) ?>">← برگشت به صفحه‌ی اصلی</a>
    </div>
</section>
