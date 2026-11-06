<?php
/** @var array $order @var array $items @var string $err */
$o = $order;
$st = $o['status'];
$S = order_statuses();
$isOwner = ($cm = current_member()) && (int) $cm['id'] === (int) $o['member_id'];
// stepper position: 1 placed · 2 payment · 3 review · 4 approved · 5 delivered
$stage = match ($st) { 'pending_payment', 'rejected' => 2, 'reviewing' => 3, 'approved' => 4, 'completed' => 5, default => 1 };
$dead = in_array($st, ['canceled', 'expired'], true);
$card = preg_replace('~\D~', '', (string) setting('shop_card_number'));
$cardFmt = trim(chunk_split($card, 4, ' '));
$left = $o['expires_at'] ? strtotime($o['expires_at']) - time() : 0;
$steps = [
    ['📝', 'ثبت سفارش', jdate('j F H:i', $o['created_at'])],
    ['💳', 'پرداخت', $o['paid_at'] ? jdate('H:i', $o['paid_at']) : ($st === 'rejected' ? 'ارسال مجدد فیش' : 'کارت‌به‌کارت')],
    ['🔎', 'بررسی فیش', $st === 'reviewing' ? 'در حال بررسی…' : ($o['approved_at'] ? 'تایید شد' : '')],
    ['⚙️', 'انجام سفارش', $o['approved_at'] && $st !== 'completed' ? 'در حال انجام…' : ($o['approved_at'] ? jdate('H:i', $o['approved_at']) : '')],
    ['🎁', 'تحویل', $o['completed_at'] ? jdate('j F H:i', $o['completed_at']) : ''],
];
?>
<section class="container shop-page order-page st-<?= e($st) ?>" id="orderPage" data-code="<?= e($o['code']) ?>" data-status="<?= e($st) ?>" data-v="<?= strtotime($o['updated_at']) ?>" data-final="<?= in_array($st, ['completed', 'canceled', 'expired'], true) ? 1 : 0 ?>">
    <div class="op-head glass" data-reveal>
        <div class="op-code">
            <small>شماره سفارش</small>
            <b dir="ltr" data-copy="<?= e($o['code']) ?>" title="کپی"><?= e($o['code']) ?> <?= icon('copy', 15) ?></b>
        </div>
        <div class="op-status tone-<?= e($S[$st]['tone'] ?? 'info') ?>"><span class="ops-dot"></span><?= e($S[$st]['icon'] ?? '') ?> <?= e($S[$st]['name'] ?? $st) ?></div>
        <div class="op-sum"><small>مبلغ سفارش</small><b><?= money((int) $o['total']) ?> <small>تومان</small></b></div>
    </div>

    <?php if (!$dead): ?>
    <ol class="order-steps stage-<?= $stage ?>" style="--p:<?= ($stage - 1) / 4 ?>" data-reveal>
        <?php foreach ($steps as $i => [$ic, $name, $sub]): $n = $i + 1;
            $cls = $n < $stage || ($n === 5 && $st === 'completed') ? 'done' : ($n === $stage ? 'now' : ''); ?>
            <li class="<?= $cls ?>" style="--i:<?= $i ?>">
                <span class="os-ic"><i class="os-emo"><?= $ic ?></i><svg class="os-check" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.2 4.2L19 7"/></svg><i class="os-pulse" aria-hidden="true"></i></span>
                <b><?= e($name) ?></b><?php if ($sub): ?><small><?= e($sub) ?></small><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <span class="os-beam" aria-hidden="true"></span>
    </ol>
    <?php endif; ?>

    <?php if (!empty($_GET['sent']) && $st === 'reviewing'): ?><div class="alert alert-success" data-reveal>✅ فیش شما با موفقیت ارسال شد. نتیجه بررسی همین‌جا و در اعلان‌ها نمایش داده می‌شود.</div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error" data-reveal><?= e($err) ?></div><?php endif; ?>

    <div class="cart-grid">
        <div class="op-main">
        <?php if (in_array($st, ['pending_payment', 'rejected'], true) && $isOwner): ?>
            <?php if ($st === 'rejected'): ?>
                <div class="op-alert tone-error glass" data-reveal><span>⚠️</span><div><b>فیش ارسالی تایید نشد</b><p><?= e($o['reject_reason'] ?: 'لطفاً تصویر صحیح فیش واریزی را دوباره ارسال کنید.') ?></p></div></div>
            <?php endif; ?>
            <div class="pay-box glass" data-reveal>
                <h3><?= icon('card', 20) ?> مبلغ را به این کارت واریز کنید</h3>
                <div class="bank-card" id="bankCard">
                    <span class="bc-shine" aria-hidden="true"></span><span class="bc-orb" aria-hidden="true"></span>
                    <div class="bc-top"><b><?= e(setting('shop_card_bank', '')) ?></b><span class="bc-chip" aria-hidden="true"></span></div>
                    <div class="bc-num" dir="ltr"><?php foreach (explode(' ', $cardFmt) as $g): ?><span><?= e($g) ?></span><?php endforeach; ?></div>
                    <div class="bc-bottom"><div><small>به نام</small><b><?= e(setting('shop_card_name')) ?></b></div><span class="bc-brand"><img src="<?= e(logo_url()) ?>" alt="">NEXUS</span></div>
                </div>
                <div class="pay-copy">
                    <button type="button" class="copy-tile" data-copy="<?= e($card) ?>" data-copy-msg="شماره کارت کپی شد ✅">
                        <span class="ct-ic"><?= icon('card', 20) ?></span><span><small>شماره کارت</small><b dir="ltr"><?= e($cardFmt) ?></b></span><span class="ct-act"><?= icon('copy', 16) ?> کپی</span>
                    </button>
                    <button type="button" class="copy-tile ct-amount" data-copy="<?= (int) $o['total'] ?>" data-copy-msg="مبلغ کپی شد ✅">
                        <span class="ct-ic">﷼</span><span><small>مبلغ (تومان)</small><b><?= money((int) $o['total']) ?></b></span><span class="ct-act"><?= icon('copy', 16) ?> کپی</span>
                    </button>
                </div>
                <p class="pay-hint"><?= icon('sparkle', 14) ?> دقیقاً همین مبلغ را واریز کنید؛ معادل ریالی: <b><?= money((int) $o['total'] * 10) ?> ریال</b></p>
                <?php if ($st === 'pending_payment' && $left > 0): ?>
                <div class="pay-timer" data-left="<?= $left ?>"><span class="pt-ring"><svg viewBox="0 0 36 36"><circle cx="18" cy="18" r="16"/><circle class="pt-bar" cx="18" cy="18" r="16" style="--t:<?= max(1, (int) setting('shop_pay_minutes', '60')) * 60 ?>"/></svg></span><span><small>مهلت پرداخت</small><b class="pt-left" dir="ltr">--:--</b></span></div>
                <?php endif; ?>
            </div>

            <form class="receipt-box glass" method="post" enctype="multipart/form-data" data-reveal id="receiptForm">
                <?= csrf_field() ?><input type="hidden" name="act" value="receipt">
                <h3><?= icon('receipt', 20) ?> ارسال فیش واریزی</h3>
                <label class="drop" id="dropZone">
                    <input type="file" name="receipt" accept="image/*" required id="receiptIn">
                    <span class="drop-empty"><span class="drop-ic"><?= icon('upload', 30) ?></span><b>تصویر فیش را اینجا بکشید یا انتخاب کنید</b><small>اسکرین‌شات رسید انتقال وجه · JPG / PNG · حداکثر ۸ مگابایت</small></span>
                    <span class="drop-prev" hidden><img alt="پیش‌نمایش فیش"><em>تغییر تصویر</em></span>
                </label>
                <label class="field"><span>شماره پیگیری / ۴ رقم آخر کارت مبدأ (اختیاری)</span><input name="ref" dir="ltr" maxlength="40" inputmode="numeric" placeholder="مثلاً 123456"></label>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><?= icon('send', 18) ?> ارسال فیش و پیگیری سفارش</button>
            </form>
            <?php if ($st === 'pending_payment'): ?>
            <form method="post" class="op-cancel" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="act" value="cancel"><button type="submit" class="link-btn">لغو سفارش</button></form>
            <?php endif; ?>

        <?php elseif ($st === 'reviewing'): ?>
            <div class="state-card glass" data-reveal>
                <div class="scan-radar" aria-hidden="true"><span class="sr-sweep"></span><span class="sr-ring"></span><span class="sr-ring r2"></span><span class="sr-core"><?= icon('receipt', 34) ?></span><i></i><i></i><i></i></div>
                <h2>فیش شما در حال بررسی است</h2>
                <p>پشتیبانی NEXUS در حال تایید واریز شماست. معمولاً کمتر از ۳۰ دقیقه طول می‌کشد.</p>
                <span class="live-note"><span class="live-dot"></span> این صفحه خودکار به‌روز می‌شود — لازم نیست رفرش کنید</span>
            </div>
        <?php elseif ($st === 'approved'): ?>
            <div class="state-card glass" data-reveal>
                <div class="proc-anim" aria-hidden="true"><span class="pa-gear g1">⚙️</span><span class="pa-gear g2">⚙️</span><span class="pa-bar"><i></i></span></div>
                <h2>پرداخت تایید شد ✅</h2>
                <p>سفارش شما در صف انجام است و به‌زودی تحویل داده می‌شود.</p>
                <span class="live-note"><span class="live-dot"></span> به محض انجام، اعلان دریافت می‌کنید</span>
            </div>
        <?php elseif ($st === 'completed'): ?>
            <div class="state-card done-card glass" data-reveal>
                <div class="win-burst" aria-hidden="true"><span class="wb-ring"></span><span class="wb-ring r2"></span><span class="wb-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M15 27l7.5 7.5L38 19"/></svg></span><?= str_repeat('<i></i>', 14) ?></div>
                <h2>سفارش با موفقیت انجام شد 🎉</h2>
                <p>از خریدت ممنونیم! اگر سوالی داری از بخش پشتیبانی در خدمتیم.</p>
            </div>
            <?php if (trim((string) $o['delivery']) !== ''): ?>
            <div class="delivery-box glass" data-reveal>
                <h3><?= icon('box', 20) ?> اطلاعات تحویل سفارش</h3>
                <pre dir="auto"><?= e($o['delivery']) ?></pre>
                <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($o['delivery']) ?>" data-copy-msg="کپی شد ✅"><?= icon('copy', 16) ?> کپی</button>
            </div>
            <?php endif; ?>
        <?php elseif ($dead): ?>
            <div class="state-card glass" data-reveal>
                <div class="dead-ic"><?= $st === 'expired' ? '⌛' : '✖️' ?></div>
                <h2><?= $st === 'expired' ? 'مهلت پرداخت این سفارش تمام شد' : 'این سفارش لغو شد' ?></h2>
                <?php if ($o['reject_reason']): ?><p><?= e($o['reject_reason']) ?></p><?php endif; ?>
                <a class="btn btn-primary" href="<?= e(url('shop')) ?>"><?= icon('bag', 18) ?> سفارش دوباره</a>
            </div>
        <?php else: ?>
            <div class="state-card glass"><h2><?= e($S[$st]['name'] ?? $st) ?></h2></div>
        <?php endif; ?>
        </div>

        <aside class="cart-sum glass" data-reveal>
            <h3>جزئیات سفارش</h3>
            <?php foreach ($items as $it): ?>
                <div class="co-line">
                    <span class="co-ic"><?= e($it['icon'] ?: '🛍️') ?></span>
                    <span class="co-t"><b><?= e($it['title']) ?></b><small><?= e((string) $it['variant_title']) ?><?= $it['qty'] > 1 ? ' × ' . fa_num((string) $it['qty']) : '' ?></small>
                        <?php foreach (json_decode((string) $it['fields'], true) ?: [] as [$lab, $val]): ?><small class="co-f"><?= e($lab) ?>: <b dir="auto"><?= e($val) ?></b></small><?php endforeach; ?>
                    </span>
                    <b class="co-p"><?= money((int) $it['total']) ?></b>
                </div>
            <?php endforeach; ?>
            <?php if ((int) ($o['discount'] ?? 0) > 0): ?><div class="cs-row cs-disc"><span>🏷️ تخفیف جایزه</span><b>− <?= money((int) $o['discount']) ?> <small>تومان</small></b></div><?php endif; ?>
            <div class="cs-row cs-total"><span>جمع کل</span><b><?= money((int) $o['total']) ?> <small>تومان</small></b></div>
            <div class="cs-row"><span>تاریخ ثبت</span><b><?= jdate('j F Y — H:i', $o['created_at']) ?></b></div>
            <?php if ($o['receipt_ref']): ?><div class="cs-row"><span>شماره پیگیری</span><b dir="ltr"><?= e(fa_num($o['receipt_ref'])) ?></b></div><?php endif; ?>
            <?php if ($o['customer_note']): ?><div class="cs-note"><small>توضیحات شما</small><?= nl2br(e($o['customer_note'])) ?></div><?php endif; ?>
            <?php if (setting_on('shop_support_on')): ?><a class="btn btn-ghost btn-block" href="<?= e(url('support?order=' . $o['code'])) ?>"><?= icon('support', 17) ?> سوال یا مشکل؟ تیکت پشتیبانی</a><?php endif; ?>
            <a class="btn btn-ghost btn-block" href="<?= e(url('orders')) ?>"><?= icon('box', 17) ?> همه سفارش‌های من</a>
        </aside>
    </div>
</section>
