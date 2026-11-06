<?php
/**
 * فروشگاه — جزئیات سفارش، بررسی فیش، تایید / رد / انجام
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop');
shop_expire_due();

$o = row('SELECT * FROM orders WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$o) { flash('سفارش یافت نشد.', 'error'); redirect('admin/orders.php'); }
$S = order_statuses();

if (is_post()) {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 1000);
    $delivery = mb_substr(trim((string) ($_POST['delivery'] ?? '')), 0, 5000);
    switch ($act) {
        case 'approve':
            order_set_status($o, 'approved');
            flash('پرداخت تایید شد ✅ — به مشتری اعلان رفت.');
            break;
        case 'reject':
            if ($reason === '') { flash('دلیل رد فیش را بنویسید (به مشتری نمایش داده می‌شود).', 'error'); break; }
            order_set_status($o, 'rejected', ['reject_reason' => $reason]);
            flash('فیش رد شد؛ مشتری می‌تواند فیش جدید ارسال کند.');
            break;
        case 'complete':
            order_set_status($o, 'completed', ['delivery' => $delivery ?: null]);
            flash('سفارش «انجام شد» ✅ — به مشتری اعلان رفت.');
            break;
        case 'cancel':
            order_set_status($o, 'canceled', ['reject_reason' => $reason ?: 'لغو توسط پشتیبانی']);
            flash('سفارش لغو شد.');
            break;
        case 'status':
            $ns = (string) ($_POST['status'] ?? '');
            if (isset($S[$ns]) && $ns !== $o['status']) { order_set_status($o, $ns); flash('وضعیت تغییر کرد.'); }
            break;
        case 'note':
            update('orders', ['admin_note' => mb_substr(trim((string) ($_POST['admin_note'] ?? '')), 0, 3000) ?: null, 'updated_at' => $o['updated_at']], (int) $o['id']);
            flash('یادداشت ذخیره شد.');
            break;
        case 'delivery':
            update('orders', ['delivery' => $delivery ?: null], (int) $o['id']);
            flash('اطلاعات تحویل به‌روز شد.');
            break;
    }
    redirect('admin/order-view.php?id=' . $o['id']);
}

q("UPDATE notifications SET is_read = 1 WHERE audience = 'admin' AND ref_type = 'order' AND ref_id = ?", [$o['id']]);
$items = order_items((int) $o['id']);
$m = row('SELECT * FROM members WHERE id = ?', [$o['member_id']]);
$prev = (int) val("SELECT COUNT(*) FROM orders WHERE member_id = ? AND status = 'completed' AND id <> ?", [$o['member_id'], $o['id']]);
$tickets = rows('SELECT * FROM tickets WHERE order_id = ? ORDER BY id DESC', [$o['id']]);
$rates = json_decode((string) $o['rates'], true) ?: [];
$receiptUrl = $o['receipt'] ? url('order/' . $o['code'] . '/receipt') : '';
$timeline = array_filter([
    ['📝', 'ثبت سفارش', $o['created_at']],
    $o['paid_at'] ? ['🧾', 'ارسال فیش', $o['paid_at']] : null,
    $o['approved_at'] ? ['✅', 'تایید پرداخت', $o['approved_at']] : null,
    $o['completed_at'] ? ['🎁', 'انجام سفارش', $o['completed_at']] : null,
]);

adm_head('سفارش ' . $o['code'], 'orders');
?>
<div class="ov-head card mb">
    <div class="ov-top">
        <div class="ov-id"><small class="muted">شماره سفارش</small><h2 class="ltr" data-copy="<?= e($o['code']) ?>" title="کپی"><?= e($o['code']) ?></h2></div>
        <?= order_badge($o['status']) ?>
    </div>
    <div class="ov-money">
        <div class="ov-total"><small class="muted">مبلغ</small><b><?= money((int) $o['total']) ?> <small>تومان</small></b></div>
        <?php if ((int) ($o['discount'] ?? 0) > 0): ?><div class="ov-total ov-disc"><small class="muted">🏷️ تخفیف<?= $o['coupon_id'] ? ' (' . e((string) val('SELECT code FROM coupons WHERE id = ?', [$o['coupon_id']])) . ')' : '' ?></small><b>− <?= money((int) $o['discount']) ?> <small>تومان</small></b></div><?php endif; ?>
    </div>
    <div class="ov-btns">
        <a class="abtn abtn-ghost abtn-sm" href="<?= e(url('order/' . $o['code'])) ?>" target="_blank"><?= icon('eye', 15) ?> نمای مشتری</a>
        <a class="abtn abtn-ghost abtn-sm" href="orders.php">← سفارش‌ها</a>
    </div>
</div>

<div class="split">
    <div>
        <?php if (in_array($o['status'], ['reviewing', 'approved', 'rejected', 'pending_payment'], true)): ?>
        <div class="card mb ov-actions">
            <div class="card-head"><h3>⚡ اقدام</h3><span class="muted small">هر اقدام فوراً برای مشتری اعلان می‌شود</span></div>
            <?php if ($o['status'] === 'reviewing'): ?>
            <div class="ov-act-grid">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="approve">
                    <button class="abtn abtn-ok abtn-block ov-big" type="submit">✅ فیش درست است — تایید پرداخت</button>
                    <small class="muted">بعد از انجام سفارش، دکمه «انجام شد» را بزنید.</small></form>
                <form method="post" class="ov-reject"><?= csrf_field() ?><input type="hidden" name="act" value="reject">
                    <label class="af"><span class="af-label">دلیل رد فیش (برای مشتری)</span>
                        <select onchange="if(this.value)this.form.reason.value=this.value"><option value="">— انتخاب سریع —</option>
                            <option>مبلغ واریزی با مبلغ سفارش برابر نیست.</option><option>تصویر فیش خوانا نیست؛ لطفاً تصویر واضح ارسال کنید.</option>
                            <option>واریزی با این مشخصات به حساب ما نرسیده است.</option><option>فیش تکراری است.</option></select>
                        <textarea name="reason" rows="2" placeholder="یا دلیل را بنویسید..."></textarea></label>
                    <button class="abtn abtn-danger abtn-block" type="submit">❌ رد فیش</button></form>
            </div>
            <?php endif; ?>
            <?php if (in_array($o['status'], ['reviewing', 'approved'], true)): ?>
            <form method="post" class="ov-complete"><?= csrf_field() ?><input type="hidden" name="act" value="complete">
                <label class="af"><span class="af-label">🎁 اطلاعات تحویل برای مشتری (اختیاری — مثل کد گیفت کارت، لینک، توضیح)</span>
                    <textarea name="delivery" rows="3" placeholder="مثلاً: کد گیفت کارت: XXXX-XXXX-XXXX&#10;یا: پریمیوم روی اکانت @username فعال شد ✅"><?= e((string) $o['delivery']) ?></textarea></label>
                <button class="abtn abtn-primary abtn-block ov-big" type="submit" data-confirm="سفارش «انجام شد» ثبت شود؟">🎉 سفارش انجام شد</button>
            </form>
            <?php endif; ?>
            <?php if (in_array($o['status'], ['pending_payment', 'rejected'], true)): ?>
                <div class="alert alert-info small">منتظر ارسال فیش توسط مشتری<?= $o['status'] === 'pending_payment' && $o['expires_at'] ? ' — مهلت پرداخت تا ' . jdate('H:i', $o['expires_at']) : '' ?>.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($o['receipt']): ?>
        <div class="card mb">
            <div class="card-head"><h3>🧾 فیش واریزی</h3><a class="abtn abtn-ghost abtn-sm" href="<?= e($receiptUrl) ?>" target="_blank"><?= icon('external', 15) ?> اندازه کامل</a></div>
            <a href="<?= e($receiptUrl) ?>" target="_blank" class="receipt-view"><img src="<?= e($receiptUrl) ?>" alt="فیش واریزی"></a>
            <div class="ov-kv">
                <span>زمان ارسال: <b><?= $o['paid_at'] ? jdate('j F Y — H:i', $o['paid_at']) : '—' ?></b></span>
                <span>شماره پیگیری / ۴ رقم کارت: <b class="ltr"><?= e($o['receipt_ref'] ?: '—') ?></b></span>
                <span>مبلغ مورد انتظار: <b><?= money((int) $o['total']) ?> تومان (<?= money((int) $o['total'] * 10) ?> ریال)</b></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb">
            <div class="card-head"><h3>📦 اقلام سفارش</h3></div>
            <?php foreach ($items as $it): $fs = json_decode((string) $it['fields'], true) ?: []; ?>
            <div class="ov-item">
                <span class="ov-ic"><?= e($it['icon'] ?: '🛍️') ?></span>
                <div class="ov-it">
                    <b><?= e($it['title']) ?></b> <span class="muted"><?= e((string) $it['variant_title']) ?><?= $it['qty'] > 1 ? ' × ' . fa_num((string) $it['qty']) : '' ?></span>
                    <?php foreach ($fs as [$lab, $val]): ?>
                        <div class="ov-field"><span><?= e($lab) ?>:</span> <b dir="auto"><?= e($val) ?></b> <button type="button" class="adm-ib xs" data-copy="<?= e($val) ?>" title="کپی"><?= icon('copy', 13) ?></button></div>
                    <?php endforeach; ?>
                </div>
                <b class="ov-p"><?= money((int) $it['total']) ?></b>
            </div>
            <?php endforeach; ?>
            <?php if ($o['customer_note']): ?><div class="alert alert-info small mt"><b>توضیح مشتری:</b> <?= nl2br(e($o['customer_note'])) ?></div><?php endif; ?>
            <?php if ($rates): ?><p class="muted small mt">نرخ‌ها هنگام ثبت: دلار <?= !empty($rates['usd']) ? money((int) $rates['usd']) . ' ت' : '—' ?> · TON <?= !empty($rates['ton']) ? '$' . number_format((float) $rates['ton'], 3) : '—' ?> · سود <?= fa_num((string) ($rates['margin'] ?? 0)) ?>٪</p><?php endif; ?>
        </div>

        <?php if ($o['status'] === 'completed'): ?>
        <form class="card mb" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="delivery">
            <div class="card-head"><h3>🎁 اطلاعات تحویل (نمایش به مشتری)</h3></div>
            <textarea name="delivery" rows="3" class="w100 input"><?= e((string) $o['delivery']) ?></textarea>
            <button class="abtn abtn-ghost abtn-sm mt" type="submit">ذخیره</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="split-side">
        <div class="card">
            <div class="card-head"><h3>👤 مشتری</h3><?php if ($m): ?><a class="abtn abtn-ghost abtn-xs" href="member-view.php?id=<?= (int) $m['id'] ?>">پروفایل</a><?php endif; ?></div>
            <?php if ($m): ?>
            <div class="ov-kv col">
                <span>نام: <b><?= e(member_full_name($m)) ?></b></span>
                <span>موبایل: <b class="ltr"><?= e(fa_num($m['phone'])) ?></b> <button type="button" class="adm-ib xs" data-copy="<?= e($m['phone']) ?>"><?= icon('copy', 13) ?></button></span>
                <span>ایمیل: <b class="ltr"><?= e($m['email']) ?></b></span>
                <span>کد عضویت: <b class="ltr"><?= e($m['member_code']) ?></b></span>
                <span>خریدهای موفق قبلی: <b><?= fa_num((string) $prev) ?></b></span>
            </div>
            <?php else: ?><p class="muted">حساب مشتری حذف شده است.</p><?php endif; ?>
        </div>
        <div class="card mt">
            <div class="card-head"><h3>🕒 روند سفارش</h3></div>
            <ol class="ov-timeline">
                <?php foreach ($timeline as [$ic, $n, $t]): ?><li><span><?= $ic ?></span><b><?= e($n) ?></b><small><?= jdate('j F — H:i', $t) ?></small></li><?php endforeach; ?>
                <?php if (in_array($o['status'], ['rejected', 'canceled', 'expired'], true)): ?><li class="bad"><span><?= e($S[$o['status']]['icon']) ?></span><b><?= e($S[$o['status']]['name']) ?></b><small><?= e((string) $o['reject_reason']) ?></small></li><?php endif; ?>
            </ol>
        </div>
        <?php if ($tickets): ?>
        <div class="card mt">
            <div class="card-head"><h3>💬 تیکت‌های این سفارش</h3></div>
            <?php foreach ($tickets as $t): ?><a class="ov-ticket" href="ticket-view.php?id=<?= (int) $t['id'] ?>"><?= e($t['subject']) ?> <?= ticket_badge($t['status']) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <form class="card mt" method="post"><?= csrf_field() ?><input type="hidden" name="act" value="note">
            <div class="card-head"><h3>🔒 یادداشت داخلی</h3></div>
            <textarea name="admin_note" rows="3" class="w100 input" placeholder="فقط برای تیم مدیریت قابل مشاهده است"><?= e((string) $o['admin_note']) ?></textarea>
            <button class="abtn abtn-ghost abtn-sm mt" type="submit">ذخیره یادداشت</button>
        </form>
        <div class="card mt">
            <div class="card-head"><h3>⚙️ تغییر دستی وضعیت</h3></div>
            <form method="post" class="row-flex"><?= csrf_field() ?><input type="hidden" name="act" value="status">
                <select name="status" style="flex:1"><?php foreach ($S as $k => $s): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= e($s['icon'] . ' ' . $s['name']) ?></option><?php endforeach; ?></select>
                <button class="abtn abtn-ghost abtn-sm" type="submit">اعمال</button></form>
            <?php if (!in_array($o['status'], ['completed', 'canceled', 'expired'], true)): ?>
            <form method="post" class="mt" data-confirm="سفارش لغو شود؟"><?= csrf_field() ?><input type="hidden" name="act" value="cancel">
                <input name="reason" placeholder="دلیل لغو (اختیاری، برای مشتری)" class="w100 input">
                <button class="abtn abtn-danger abtn-sm mt" type="submit">لغو سفارش</button></form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php adm_foot(); ?>
