<?php
/**
 * فروشگاه — سفارش‌ها
 */
require __DIR__ . '/inc/boot.php';
require_cap('orders');
shop_expire_due();

$S = order_statuses();
$st = (string) ($_GET['status'] ?? '');
$qs = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if ($st === 'todo') $where[] = "o.status IN ('reviewing','approved')";
elseif (isset($S[$st])) { $where[] = 'o.status = ?'; $params[] = $st; }
if ($qs !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], en_num($qs)) . '%';
    $where[] = "(o.code LIKE ? OR m.phone LIKE ? OR m.username LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR o.receipt_ref LIKE ? OR o.id IN (SELECT order_id FROM order_items WHERE fields LIKE ?))";
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM orders o LEFT JOIN members m ON m.id = o.member_id WHERE $w", $params);
$pg = paginate($total, 25, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT o.*, m.first_name, m.last_name, m.phone, m.member_code FROM orders o LEFT JOIN members m ON m.id = o.member_id WHERE $w ORDER BY FIELD(o.status, 'reviewing', 'approved') DESC, o.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$counts = array_column(rows('SELECT status, COUNT(*) n FROM orders GROUP BY status'), 'n', 'status');
$today = date('Y-m-d 00:00:00');
$sum = row("SELECT COALESCE(SUM(CASE WHEN completed_at >= ? THEN total END),0) today, COALESCE(SUM(total),0) allt, COUNT(*) n FROM orders WHERE status = 'completed'", [$today]);

adm_head('سفارش‌ها', 'orders');
?>
<div class="grid g4 stats-grid mb">
    <div class="stat" style="--g:linear-gradient(135deg,#3dbbff,#7c4dff);--sh:rgba(61,187,255,.7);--c:rgba(61,187,255,.3)"><span class="stat-ic">🔎</span><div><b><?= fa_num((string) (int) ($counts['reviewing'] ?? 0)) ?></b><small>فیش در انتظار بررسی</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#ff7a3d,#ff1f3d);--sh:rgba(255,90,61,.7);--c:rgba(255,90,61,.3)"><span class="stat-ic">⚙️</span><div><b><?= fa_num((string) (int) ($counts['approved'] ?? 0)) ?></b><small>تایید شده — در حال انجام</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3ddc84,#1de9b6);--sh:rgba(61,220,132,.7);--c:rgba(61,220,132,.3)"><span class="stat-ic">💰</span><div><b><?= money((int) $sum['today']) ?></b><small>فروش امروز (تومان)</small></div></div>
    <div class="stat"><span class="stat-ic">✅</span><div><b><?= money((int) $sum['allt']) ?></b><small>کل فروش · <?= fa_num((string) (int) $sum['n']) ?> سفارش</small></div></div>
</div>

<div class="card">
    <div class="ord-tabs">
        <a href="orders.php" class="<?= $st === '' ? 'on' : '' ?>">همه <em><?= fa_num((string) array_sum($counts)) ?></em></a>
        <a href="?status=todo" class="<?= $st === 'todo' ? 'on' : '' ?>">🔥 نیاز به اقدام <em><?= fa_num((string) (($counts['reviewing'] ?? 0) + ($counts['approved'] ?? 0))) ?></em></a>
        <?php foreach ($S as $k => $s): ?><a href="?status=<?= $k ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($s['icon'] . ' ' . $s['name']) ?> <em><?= fa_num((string) (int) ($counts[$k] ?? 0)) ?></em></a><?php endforeach; ?>
    </div>
    <form class="toolbar" method="get">
        <?php if ($st): ?><input type="hidden" name="status" value="<?= e($st) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($qs) ?>" placeholder="جستجو: کد سفارش، نام، موبایل، آیدی تلگرام، شماره پیگیری..." style="flex:1;min-width:220px">
        <button class="abtn abtn-ghost abtn-sm" type="submit"><?= icon('search', 16) ?> جستجو</button>
    </form>
    <?php if (!$list): ?>
        <div class="empty-state"><span>🧾</span><?= $qs !== '' || $st ? 'سفارشی با این مشخصات نیست.' : 'هنوز سفارشی ثبت نشده است.' ?></div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>سفارش</th><th>مشتری</th><th class="hide-m">اقلام</th><th>مبلغ</th><th>وضعیت</th><th class="hide-m">زمان</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $o): $its = order_items((int) $o['id']); ?>
                <tr class="<?= $o['status'] === 'reviewing' ? 'row-hot' : '' ?>">
                    <td><a href="order-view.php?id=<?= (int) $o['id'] ?>"><b class="ltr"><?= e($o['code']) ?></b></a></td>
                    <td><?= e(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: '—') ?><br><small class="muted ltr"><?= e(fa_num((string) $o['phone'])) ?></small></td>
                    <td class="hide-m"><small><?= e(implode('، ', array_map(fn($x) => ($x['icon'] ?: '') . ' ' . $x['title'] . ($x['variant_title'] ? ' (' . $x['variant_title'] . ')' : ''), $its))) ?></small></td>
                    <td><b><?= money((int) $o['total']) ?></b></td>
                    <td><?= order_badge($o['status']) ?><?= $o['receipt'] && $o['status'] === 'reviewing' ? ' <span class="badge b-info">🧾 فیش</span>' : '' ?></td>
                    <td class="hide-m"><small><?= jago($o['updated_at']) ?></small></td>
                    <td><div class="actions"><a class="abtn abtn-<?= $o['status'] === 'reviewing' ? 'primary' : 'ghost' ?> abtn-xs" href="order-view.php?id=<?= (int) $o['id'] ?>"><?= $o['status'] === 'reviewing' ? 'بررسی فیش' : 'مشاهده' ?></a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_html($pg, url('admin/orders.php?' . http_build_query(array_filter(['status' => $st, 'q' => $qs])))) ?>
    <?php endif; ?>
</div>
<?php adm_foot(); ?>
