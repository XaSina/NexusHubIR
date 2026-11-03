<?php
/**
 * مدیریت اعضای سایت (کاربرانی که از سایت ثبت‌نام کرده‌اند)
 */
require __DIR__ . '/inc/boot.php';
require_cap('*');

$qs = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$where = ['1=1'];
$params = [];
if ($qs !== '') {
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], en_num($qs)) . '%';
    $where[] = "(member_code LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if (in_array($status, ['active', 'banned'], true)) { $where[] = 'status = ?'; $params[] = $status; }
$w = implode(' AND ', $where);

// خروجی اکسل (CSV با BOM برای نمایش درست فارسی)
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexus-members-' . jdate('Y-m-d', null, false) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['کد عضویت', 'نام', 'نام خانوادگی', 'نام کاربری', 'موبایل', 'ایمیل', 'وضعیت', 'تاریخ عضویت', 'آخرین ورود', 'تعداد ورود']);
    $st = q("SELECT * FROM members WHERE $w ORDER BY id DESC", $params);
    while ($m = $st->fetch()) {
        fputcsv($out, [$m['member_code'], $m['first_name'], $m['last_name'], $m['username'], $m['phone'], $m['email'],
            $m['status'] === 'active' ? 'فعال' : 'مسدود', jdate('Y/m/d H:i', $m['created_at'], false),
            $m['last_login'] ? jdate('Y/m/d H:i', $m['last_login'], false) : '', $m['login_count']]);
    }
    exit;
}

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'ban': q("UPDATE members SET status = 'banned' WHERE id = ?", [$id]); flash('حساب عضو مسدود شد.'); break;
        case 'unban': q("UPDATE members SET status = 'active' WHERE id = ?", [$id]); flash('حساب عضو فعال شد.'); break;
        case 'delete': q('DELETE FROM members WHERE id = ?', [$id]); flash('عضو حذف شد.'); break;
    }
    redirect('admin/members.php?' . http_build_query(array_filter(['q' => $qs, 'status' => $status, 'page' => $_GET['page'] ?? null])));
}

$total = (int) val("SELECT COUNT(*) FROM members WHERE $w", $params);
$pg = paginate($total, 25, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT * FROM members WHERE $w ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$stats = row("SELECT COUNT(*) total, SUM(created_at >= ?) today, SUM(created_at >= ?) week, SUM(status = 'banned') banned, SUM(last_login >= ?) active7 FROM members",
    [date('Y-m-d 00:00:00'), date('Y-m-d H:i:s', strtotime('-7 days')), date('Y-m-d H:i:s', strtotime('-7 days'))]);

adm_head('اعضای سایت', 'members');
?>
<div class="grid g4 stats-grid mb">
    <div class="stat"><span class="stat-ic"><?= icon('user', 24) ?></span><div><b><?= fa_num(number_format((int) $stats['total'])) ?></b><small>کل اعضا</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3ddc84,#1de9b6);--sh:rgba(61,220,132,.7);--c:rgba(61,220,132,.3)"><span class="stat-ic"><?= adm_icon('plus', 24) ?></span><div><b><?= fa_num((string) (int) $stats['today']) ?></b><small>عضو جدید امروز · <?= fa_num((string) (int) $stats['week']) ?> این هفته</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#3dbbff,#7c4dff);--sh:rgba(61,187,255,.7);--c:rgba(61,187,255,.3)"><span class="stat-ic"><?= icon('eye', 24) ?></span><div><b><?= fa_num((string) (int) $stats['active7']) ?></b><small>فعال در ۷ روز اخیر</small></div></div>
    <div class="stat" style="--g:linear-gradient(135deg,#ff5a6e,#b0002a);--sh:rgba(255,90,110,.7);--c:rgba(255,90,110,.3)"><span class="stat-ic"><?= icon('close', 24) ?></span><div><b><?= fa_num((string) (int) $stats['banned']) ?></b><small>مسدود شده</small></div></div>
</div>

<div class="card">
    <form class="toolbar" method="get">
        <input type="search" name="q" value="<?= e($qs) ?>" placeholder="جستجو: نام، موبایل، ایمیل، نام کاربری یا کد عضویت..." style="flex:1;min-width:220px">
        <select name="status">
            <option value="">همه وضعیت‌ها</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعال</option>
            <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>مسدود</option>
        </select>
        <button class="abtn abtn-ghost abtn-sm" type="submit"><?= icon('search', 16) ?> جستجو</button>
        <a class="abtn abtn-primary abtn-sm" href="?<?= e(http_build_query(array_filter(['q' => $qs, 'status' => $status]) + ['export' => 'csv'])) ?>"><?= adm_icon('upload', 16) ?> خروجی اکسل</a>
    </form>

    <?php if (!$list): ?>
        <div class="empty-state"><span>👥</span><?= $qs !== '' ? 'عضوی با این مشخصات پیدا نشد.' : 'هنوز کسی در سایت ثبت‌نام نکرده است.<br><small>دکمه «ورود | ثبت‌نام» در هدر سایت قرار دارد.</small>' ?></div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>عضو</th><th>کد عضویت</th><th class="hide-m">موبایل</th><th class="hide-m">ایمیل</th><th class="hide-m">عضویت</th><th class="hide-m">آخرین ورود</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $m): ?>
                <tr>
                    <td>
                        <a class="row-flex" style="gap:10px;flex-wrap:nowrap" href="member-view.php?id=<?= (int) $m['id'] ?>">
                            <span class="avatar avatar-letter" style="width:38px;height:38px"><?= e(mb_substr($m['first_name'], 0, 1)) ?></span>
                            <span><b class="t-title"><?= e(member_full_name($m)) ?></b><small class="muted ltr" style="display:block">@<?= e($m['username']) ?></small></span>
                        </a>
                    </td>
                    <td><span class="badge b-hot ltr" data-copy="<?= e($m['member_code']) ?>" style="cursor:pointer" title="کپی"><?= e($m['member_code']) ?></span></td>
                    <td class="hide-m ltr small"><?= e($m['phone']) ?></td>
                    <td class="hide-m ltr small"><?= e($m['email']) ?></td>
                    <td class="hide-m small muted"><?= jdate('j F Y', $m['created_at']) ?></td>
                    <td class="hide-m small muted"><?= $m['last_login'] ? jago($m['last_login']) : '—' ?></td>
                    <td><?= $m['status'] === 'active' ? '<span class="badge b-ok">فعال</span>' : '<span class="badge b-err">مسدود</span>' ?></td>
                    <td><form method="post" class="actions">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <a class="adm-ib" href="member-view.php?id=<?= (int) $m['id'] ?>" title="مشاهده و ویرایش"><?= icon('eye', 16) ?></a>
                        <?php if ($m['status'] === 'active'): ?>
                            <button class="adm-ib" name="act" value="ban" title="مسدود کردن" data-confirm="حساب «<?= e(member_full_name($m)) ?>» مسدود شود؟"><?= icon('close', 16) ?></button>
                        <?php else: ?>
                            <button class="adm-ib" name="act" value="unban" title="رفع مسدودی"><?= icon('check', 16) ?></button>
                        <?php endif; ?>
                        <button class="adm-ib" name="act" value="delete" title="حذف" data-confirm="عضو «<?= e(member_full_name($m)) ?>» برای همیشه حذف شود؟"><?= adm_icon('trash', 16) ?></button>
                    </form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= pagination_html($pg, url('admin/members.php?' . http_build_query(array_filter(['q' => $qs, 'status' => $status])))) ?>
</div>
<?php adm_foot();
