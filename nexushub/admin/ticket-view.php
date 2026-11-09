<?php
/**
 * فروشگاه — گفتگوی تیکت و پاسخ
 */
require __DIR__ . '/inc/boot.php';
require_cap('tickets');

$t = row('SELECT * FROM tickets WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$t) { flash('تیکت یافت نشد.', 'error'); redirect('admin/tickets.php'); }

if (is_post()) {
    csrf_check();
    $act = (string) ($_POST['act'] ?? 'reply');
    if ($act === 'close') {
        q("UPDATE tickets SET status = 'closed', updated_at = ? WHERE id = ?", [now(), $t['id']]);
        unnotify('admin', 'ticket', (int) $t['id']);
        notify('m:' . $t['member_id'], 'ticket', (int) $t['id'], '✔️', 'تیکت «' . $t['subject'] . '» بسته شد', 'اگر سوال دیگری دارید تیکت جدید ثبت کنید.', 'support/' . $t['code'], 'muted', date('Y-m-d H:i:s', time() + 86400));
        flash('تیکت بسته شد.');
    } elseif ($act === 'reopen') {
        q("UPDATE tickets SET status = 'open', updated_at = ? WHERE id = ?", [now(), $t['id']]);
        flash('تیکت دوباره باز شد.');
    } else {
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 4000);
        $att = null;
        if (!empty($_FILES['file']['name'])) {
            $up = upload_private_image($_FILES['file'], 'tickets');
            if (!$up['ok']) { flash($up['error'], 'error'); redirect('admin/ticket-view.php?id=' . $t['id']); }
            $att = $up['path'];
        }
        if (mb_strlen($body) < 1 && !$att) { flash('متن پاسخ را بنویسید.', 'error'); redirect('admin/ticket-view.php?id=' . $t['id']); }
        ticket_add_message($t, 'admin', $body ?: '📎 تصویر پیوست', $att, (int) current_user()['id']);
        flash('پاسخ ارسال شد ✅ — به کاربر اعلان رفت.');
    }
    redirect('admin/ticket-view.php?id=' . $t['id'] . '#end');
}

q("UPDATE notifications SET is_read = 1 WHERE audience = 'admin' AND ref_type = 'ticket' AND ref_id = ?", [$t['id']]);
$msgs = rows('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$t['id']]);
$m = row('SELECT * FROM members WHERE id = ?', [$t['member_id']]);
$o = $t['order_id'] ? row('SELECT * FROM orders WHERE id = ?', [$t['order_id']]) : null;
$canned = ['سلام، وقت بخیر 🌹', 'سفارش شما در حال انجام است و به‌زودی تحویل داده می‌شود.', 'لطفاً تصویر فیش واریزی را ارسال کنید.', 'مشکل برطرف شد ✅ اگر سوال دیگری دارید در خدمتیم.'];

adm_head('تیکت: ' . $t['subject'], 'tickets');
?>
<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>💬 <?= e($t['subject']) ?> <small class="muted ltr"><?= e($t['code']) ?></small></h3><?= ticket_badge($t['status']) ?></div>
            <div class="adm-chat">
                <?php foreach ($msgs as $x): $sup = $x['sender'] === 'admin'; ?>
                    <div class="ac-msg <?= $sup ? 'sup' : 'mem' ?>">
                        <b><?= $sup ? '🛡️ پشتیبانی' : '👤 ' . e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></b>
                        <div><?= nl2br(e($x['body'])) ?></div>
                        <?php if ($x['attachment']): $src = url('support/' . $t['code'] . '/file/' . $x['id']); ?><a href="<?= e($src) ?>" target="_blank"><img src="<?= e($src) ?>" alt="پیوست"></a><?php endif; ?>
                        <small><?= jdate('j F — H:i', $x['created_at']) ?></small>
                    </div>
                <?php endforeach; ?>
                <span id="end"></span>
            </div>
        </div>
        <form class="card mt" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="act" value="reply">
            <div class="card-head"><h3>✍️ پاسخ</h3></div>
            <div class="row-flex mb" style="gap:6px;flex-wrap:wrap">
                <?php foreach ($canned as $cn): ?><button type="button" class="abtn abtn-ghost abtn-xs" onclick="var t=this.form.body;t.value=(t.value?t.value+'\n':'')+this.dataset.t;t.focus()" data-t="<?= e($cn) ?>"><?= e(mb_strimwidth($cn, 0, 34, '…')) ?></button><?php endforeach; ?>
            </div>
            <textarea name="body" rows="4" class="w100 input" placeholder="پاسخ به کاربر..."></textarea>
            <div class="row-flex mt" style="justify-content:space-between">
                <label class="abtn abtn-ghost abtn-sm"><?= adm_icon('upload', 15) ?> پیوست تصویر<input type="file" name="file" accept="image/*" hidden></label>
                <button class="abtn abtn-primary" type="submit"><?= icon('send', 16) ?> ارسال پاسخ</button>
            </div>
        </form>
    </div>
    <div class="split-side">
        <div class="card">
            <div class="card-head"><h3>👤 کاربر</h3><?php if ($m): ?><a class="abtn abtn-ghost abtn-xs" href="member-view.php?id=<?= (int) $m['id'] ?>">پروفایل</a><?php endif; ?></div>
            <?php if ($m): ?><div class="ov-kv col">
                <span>نام: <b><?= e(member_full_name($m)) ?></b></span>
                <span>موبایل: <b class="ltr"><?= e(fa_num($m['phone'])) ?></b></span>
                <span>کد عضویت: <b class="ltr"><?= e($m['member_code']) ?></b></span>
            </div><?php endif; ?>
            <?php if ($o): ?><a class="abtn abtn-ghost abtn-block mt" href="order-view.php?id=<?= (int) $o['id'] ?>">🧾 سفارش <?= e($o['code']) ?> — <?= order_badge($o['status']) ?></a><?php endif; ?>
        </div>
        <div class="card mt">
            <form method="post"><?= csrf_field() ?>
                <?php if ($t['status'] !== 'closed'): ?><button class="abtn abtn-ghost abtn-block" name="act" value="close" type="submit">✔️ بستن تیکت</button>
                <?php else: ?><button class="abtn abtn-ghost abtn-block" name="act" value="reopen" type="submit">↺ باز کردن دوباره</button><?php endif; ?>
            </form>
            <a class="abtn abtn-ghost abtn-block mt" href="tickets.php">← همه تیکت‌ها</a>
        </div>
    </div>
</div>
<?php adm_foot(); ?>
