<?php
/**
 * فروشگاه — تیکت‌های پشتیبانی
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop');

$st = (string) ($_GET['status'] ?? 'open');
$T = ticket_statuses();
$where = isset($T[$st]) ? 't.status = ?' : '1=1';
$params = isset($T[$st]) ? [$st] : [];
$list = rows("SELECT t.*, m.first_name, m.last_name, m.phone, o.code AS order_code,
    (SELECT COUNT(*) FROM ticket_messages x WHERE x.ticket_id = t.id) AS msgs,
    (SELECT body FROM ticket_messages x WHERE x.ticket_id = t.id ORDER BY x.id DESC LIMIT 1) AS last_body
    FROM tickets t LEFT JOIN members m ON m.id = t.member_id LEFT JOIN orders o ON o.id = t.order_id
    WHERE $where ORDER BY t.updated_at DESC LIMIT 200", $params);
$counts = array_column(rows('SELECT status, COUNT(*) n FROM tickets GROUP BY status'), 'n', 'status');

adm_head('تیکت‌های پشتیبانی', 'tickets');
?>
<div class="card">
    <div class="ord-tabs">
        <?php foreach ($T as $k => $s): ?><a href="?status=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($s['name']) ?> <em><?= fa_num((string) (int) ($counts[$k] ?? 0)) ?></em></a><?php endforeach; ?>
        <a href="?status=all" class="<?= !isset($T[$st]) ? 'on' : '' ?>">همه <em><?= fa_num((string) array_sum($counts)) ?></em></a>
    </div>
    <?php if (!setting_on('shop_support_on')): ?><div class="alert alert-warn mb">بخش پشتیبانی در سایت خاموش است (تنظیمات فروشگاه).</div><?php endif; ?>
    <?php if (!$list): ?>
        <div class="empty-state"><span>💬</span><?= $st === 'open' ? 'تیکت بی‌پاسخی ندارید 🎉' : 'تیکتی نیست.' ?></div>
    <?php else: ?>
    <div class="tk-list">
        <?php foreach ($list as $t): ?>
            <a class="tk-row<?= $t['status'] === 'open' ? ' hot' : '' ?>" href="ticket-view.php?id=<?= (int) $t['id'] ?>">
                <span class="tk-av"><?= e(mb_substr((string) $t['first_name'], 0, 1) ?: '؟') ?></span>
                <span class="tk-main"><b><?= e($t['subject']) ?></b><small><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?><?= $t['order_code'] ? ' · سفارش ' . e($t['order_code']) : '' ?> · <?= fa_num((string) $t['msgs']) ?> پیام</small><small class="tk-last"><?= e(mb_strimwidth((string) $t['last_body'], 0, 110, '…')) ?></small></span>
                <span class="tk-side"><?= ticket_badge($t['status']) ?><small><?= jago($t['updated_at']) ?></small></span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php adm_foot(); ?>
