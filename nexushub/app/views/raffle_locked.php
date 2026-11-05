<?php
/** @var array $raffle */
$r = $raffle;
$rule = (string) setting('raffle_ended_access', 'winners');
$me = current_member();
?>
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container rf-page">
    <div class="rf-lockpage glass rf-in">
        <span class="rf-lock-ic">🔒</span>
        <h1><?= e($r['title']) ?></h1>
        <p class="muted"><?= $r['status'] === 'failed' ? 'این قرعه‌کشی به حد نصاب نرسید و برگزار نشد.' : 'این قرعه‌کشی تموم شده و نتیجه‌ش فقط برای ' . ($rule === 'participants' ? 'شرکت‌کننده‌ها' : 'برنده‌ها') . ' قابل دیدنه.' ?></p>
        <?php if (!$me): ?><p class="small muted">اگه برنده یا شرکت‌کننده بودی، وارد حسابت شو.</p><a class="btn btn-primary" href="<?= e(url('auth')) ?>" data-warp>ورود به حساب</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('raffle')) ?>">← بقیه‌ی قرعه‌کشی‌ها</a>
    </div>
</section>
