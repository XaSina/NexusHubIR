<?php
/** @var array $tickets @var array $myOrders @var string $err @var array $old @var string $pre */
$TS = ticket_statuses();
$preId = 0;
foreach ($myOrders as $mo) if ($mo['code'] === strtoupper($pre)) $preId = (int) $mo['id'];
$open = $err !== '' || $preId || !$tickets;
?>
<section class="container shop-page">
    <div class="support-hero glass" data-reveal>
        <div class="suh-ic"><?= icon('support', 38) ?><span class="suh-wave"></span><span class="suh-wave w2"></span></div>
        <div>
            <span class="eyebrow"><?= icon('sparkle', 14) ?> پشتیبانی فروشگاه</span>
            <h1>چطور می‌تونیم کمکت کنیم؟</h1>
            <p class="muted">سوال قبل از خرید، پیگیری سفارش یا هر مشکلی داری تیکت بزن؛ پاسخ پشتیبانی همین‌جا و در اعلان‌ها بهت میرسه.</p>
        </div>
        <button class="btn btn-primary" type="button" data-toggle="#newTicket"><?= icon('plus', 18) ?> تیکت جدید</button>
    </div>

    <form class="nt-form glass" id="newTicket" method="post" enctype="multipart/form-data" <?= $open ? '' : 'hidden' ?> data-reveal>
        <?= csrf_field() ?>
        <h3><?= icon('send', 18) ?> ثبت تیکت جدید</h3>
        <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
        <div class="form-row">
            <label class="field"><span>موضوع *</span><input name="subject" maxlength="150" required value="<?= e($old['subject'] ?? ($preId ? 'پیگیری سفارش ' . strtoupper($pre) : '')) ?>" placeholder="مثلاً: سوال درباره پریمیوم ۳ ماهه"></label>
            <label class="field"><span>مربوط به سفارش</span>
                <select name="order_id"><option value="0">— بدون سفارش —</option>
                    <?php foreach ($myOrders as $mo): ?><option value="<?= (int) $mo['id'] ?>" <?= (int) ($old['order_id'] ?? $preId) === (int) $mo['id'] ? 'selected' : '' ?>><?= e($mo['code']) ?> · <?= money((int) $mo['total']) ?> تومان · <?= jdate('j F', $mo['created_at']) ?></option><?php endforeach; ?>
                </select></label>
        </div>
        <label class="field"><span>متن پیام *</span><textarea name="body" rows="5" maxlength="4000" required placeholder="پیامت رو کامل بنویس..."><?= e($old['body'] ?? '') ?></textarea></label>
        <div class="nt-foot">
            <label class="attach-btn"><?= icon('upload', 17) ?> <span>پیوست تصویر (اختیاری)</span><input type="file" name="file" accept="image/*" hidden></label>
            <button class="btn btn-primary" type="submit"><?= icon('send', 17) ?> ارسال تیکت</button>
        </div>
    </form>

    <div class="sp-head" data-reveal><h2 class="sec-title"><span class="sec-icon"><?= icon('comment', 18) ?></span> تیکت‌های من</h2></div>
    <?php if (!$tickets): ?>
        <div class="shop-empty glass" data-reveal><span>💬</span><b>هنوز تیکتی نداری</b><p class="muted">هر سوالی داری با دکمه «تیکت جدید» بپرس.</p></div>
    <?php else: ?>
    <div class="olist">
        <?php foreach ($tickets as $i => $t): $s = $TS[$t['status']] ?? $TS['open']; ?>
            <a class="orow glass" href="<?= e(url('support/' . $t['code'])) ?>" data-reveal style="--d:<?= $i % 8 ?>">
                <span class="or-ic">💬</span>
                <span class="or-main"><b><?= e($t['subject']) ?></b><small dir="ltr"><?= e($t['code']) ?></small><small><?= jago($t['updated_at']) ?> · <?= fa_num((string) $t['msgs']) ?> پیام</small></span>
                <span class="or-status tone-<?= e($s['tone']) ?>"><?= e($s['name']) ?></span>
                <span class="ps-go"><?= icon('arrow-left', 18) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
