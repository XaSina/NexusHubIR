<?php
/** @var array $ticket @var array $msgs @var ?array $tOrder @var string $err */
$t = $ticket;
$TS = ticket_statuses();
$s = $TS[$t['status']] ?? $TS['open'];
$mine = ($cm = current_member()) && (int) $cm['id'] === (int) $t['member_id'];
$m = row('SELECT first_name, last_name FROM members WHERE id = ?', [$t['member_id']]);
$last = $msgs ? (int) end($msgs)['id'] : 0;
?>
<section class="container shop-page ticket-page" id="ticketPage" data-code="<?= e($t['code']) ?>" data-last="<?= $last ?>">
    <div class="op-head glass" data-reveal>
        <div class="op-code"><small>تیکت <span dir="ltr"><?= e($t['code']) ?></span></small><b class="tk-subj"><?= e($t['subject']) ?></b></div>
        <div class="op-status tone-<?= e($s['tone']) ?>"><span class="ops-dot"></span><?= e($s['name']) ?></div>
        <?php if ($tOrder): ?><a class="btn btn-ghost btn-sm" href="<?= e(order_url($tOrder)) ?>"><?= icon('box', 16) ?> سفارش <?= e($tOrder['code']) ?></a><?php endif; ?>
    </div>

    <div class="chat glass" data-reveal>
        <?php foreach ($msgs as $i => $msg): $me = $msg['sender'] === 'member'; ?>
            <div class="bubble <?= $me ? 'b-me' : 'b-sup' ?>" style="--i:<?= $i ?>">
                <span class="b-av"><?= $me ? e(mb_substr($m['first_name'] ?? '؟', 0, 1)) : '<img src="' . e(logo_url()) . '" alt="">' ?></span>
                <div class="b-body">
                    <span class="b-who"><?= $me ? e(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) : 'پشتیبانی NEXUS <i class="b-badge">✓</i>' ?></span>
                    <div class="b-text"><?= nl2br(e($msg['body'])) ?></div>
                    <?php if ($msg['attachment']): $src = url('support/' . $t['code'] . '/file/' . $msg['id']); ?><a class="b-att" href="<?= e($src) ?>" target="_blank"><img src="<?= e($src) ?>" alt="پیوست" loading="lazy"></a><?php endif; ?>
                    <small class="b-time"><?= jdate('j F — H:i', $msg['created_at']) ?></small>
                </div>
            </div>
        <?php endforeach; ?>
        <span id="end"></span>
    </div>

    <?php if ($mine && $t['status'] !== 'closed'): ?>
    <form class="chat-reply glass" method="post" enctype="multipart/form-data" data-reveal>
        <?= csrf_field() ?>
        <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
        <textarea name="body" rows="3" maxlength="4000" required placeholder="پاسخ خود را بنویسید..."></textarea>
        <div class="nt-foot">
            <label class="attach-btn"><?= icon('upload', 17) ?> <span>پیوست تصویر</span><input type="file" name="file" accept="image/*" hidden></label>
            <button class="btn btn-primary" type="submit"><?= icon('send', 17) ?> ارسال</button>
        </div>
    </form>
    <form method="post" class="op-cancel" onsubmit="return confirm('تیکت بسته شود؟')"><?= csrf_field() ?><input type="hidden" name="act" value="close"><button type="submit" class="link-btn">مشکلم حل شد — بستن تیکت</button></form>
    <?php elseif ($t['status'] === 'closed'): ?>
        <p class="muted center" data-reveal>این تیکت بسته شده است. برای سوال جدید <a href="<?= e(url('support')) ?>">تیکت جدید</a> ثبت کنید.</p>
    <?php endif; ?>
</section>
