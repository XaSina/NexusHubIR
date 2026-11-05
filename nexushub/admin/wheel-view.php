<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$id = (int) ($_GET['id'] ?? 0);
$w = row('SELECT * FROM wheels WHERE id = ?', [$id]);
if (!$w) { flash('گردونه پیدا نشد.', 'error'); redirect('admin/wheels.php'); }

if (is_post()) {
    if (($_POST['ajax'] ?? '') === 'spin') {   // the owner's spin (panel) — JSON
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_valid()) { echo json_encode(['ok' => false, 'error' => 'نشست منقضی شده؛ صفحه رو رفرش کن.']); exit; }
        $res = wheel_spin($w, null);
        echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
    }
    csrf_check();
    switch ($_POST['act'] ?? '') {
        case 'grant':
            $who = trim((string) en_num((string) ($_POST['who'] ?? '')));
            $m = $who !== '' ? row('SELECT * FROM members WHERE member_code = ? OR phone = ? OR username = ? LIMIT 1', [$who, $who, $who]) : null;
            $n = max(1, min(100, (int) en_num((string) ($_POST['spins'] ?? 1))));
            if (!$m) { flash('عضوی با این کد / موبایل / نام کاربری پیدا نشد.', 'error'); break; }
            insert('wheel_grants', ['wheel_id' => $id, 'member_id' => $m['id'], 'spins' => $n, 'note' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200) ?: 'هدیه‌ی مدیر', 'created_at' => now()]);
            notify('m:' . $m['id'], 'wheel', $id, '🎁', fa_num((string) $n) . ' چرخش هدیه گرفتی!', 'برای «' . $w['title'] . '» — برو بچرخون 🎡', 'wheel/' . $w['slug'], 'success');
            flash(fa_num((string) $n) . ' چرخش به ' . member_full_name($m) . ' هدیه داده شد 🎁');
            break;
        case 'deliver':
            q("UPDATE wheel_spins SET status = IF(status = 'delivered', 'new', 'delivered') WHERE id = ? AND wheel_id = ?", [(int) ($_POST['spin'] ?? 0), $id]);
            break;
        case 'clear-admin':
            q("DELETE FROM wheel_spins WHERE wheel_id = ? AND src = 'admin'", [$id]);
            flash('چرخش‌های خودت پاک شد.');
            break;
    }
    redirect('admin/wheel-view.php?id=' . $id);
}

$st = wheel_state($w, null, true);
$tot = (int) val('SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL', [$id]);
$won = (int) val("SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL AND kind = 'prize'", [$id]);
$todo = (int) val("SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL AND kind = 'prize' AND status = 'new'", [$id]);
$people = (int) val('SELECT COUNT(DISTINCT member_id) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL', [$id]);
$hist = rows('SELECT s.*, m.first_name, m.last_name, m.phone, m.member_code FROM wheel_spins s LEFT JOIN members m ON m.id = s.member_id WHERE s.wheel_id = ? ORDER BY s.id DESC LIMIT 150', [$id]);
$grants = rows('SELECT g.*, m.first_name, m.last_name, m.member_code FROM wheel_grants g JOIN members m ON m.id = g.member_id WHERE g.wheel_id = ? ORDER BY g.id DESC LIMIT 30', [$id]);
$private = $w['audience'] === 'private';
$badge = ['draft' => ['b-muted', 'پیش‌نویس'], 'active' => ['b-ok', 'فعال'], 'paused' => ['b-warn', 'متوقف']][$w['status']] ?? ['', $w['status']];
adm_head($w['title'], 'wheels');
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/luck.css')) ?>">
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🎡</span><div><h2><?= e($w['title']) ?> <?php if ($private): ?><span class="badge">🔒 فقط برای خودم</span><?php else: ?><span class="badge <?= $badge[0] ?>"><?= e($badge[1]) ?></span><?php endif; ?></h2>
        <small><?= $private ? 'گردونه‌ی شخصی — فقط اینجا می‌چرخه' : fa_num((string) $tot) . ' چرخش · ' . fa_num((string) $people) . ' نفر · ' . fa_num((string) $won) . ' جایزه' ?></small></div></div>
    <div class="row-flex">
        <?php if (!$private): ?><a class="abtn abtn-sm" href="<?= e(wheel_url($w)) ?>" target="_blank"><?= icon('external', 15) ?> صفحه روی سایت</a><?php endif; ?>
        <a class="abtn abtn-sm" href="wheel-edit.php?id=<?= $id ?>"><?= adm_icon('edit', 15) ?> ویرایش</a>
    </div>
</div>

<?php if (!$private): ?>
<div class="grid g4 stats-grid mb">
    <div class="stat"><span class="stat-ic">🎡</span><div><b><?= fa_num((string) $tot) ?></b><small>چرخش کاربرها</small></div></div>
    <div class="stat"><span class="stat-ic">👥</span><div><b><?= fa_num((string) $people) ?></b><small>نفر چرخوندن</small></div></div>
    <div class="stat"><span class="stat-ic">🏆</span><div><b><?= fa_num((string) $won) ?></b><small>جایزه برده شده</small></div></div>
    <div class="stat"><span class="stat-ic"><?= $todo ? '📦' : '✅' ?></span><div><b><?= fa_num((string) $todo) ?></b><small>جایزه‌ی تحویل‌نشده</small></div></div>
</div>
<?php endif; ?>

<div class="card mb rf-admin-card lw-admin">
    <div class="card-head"><h3>🎡 بچرخون</h3><small class="muted"><?= $private ? 'هر چند بار خواستی؛ نتیجه‌ها پایین ثبت می‌شه' : 'چرخش آزمایشی: از موجودی جایزه‌ها کم نمی‌شه' ?></small></div>
    <div class="rf-admin-wheel">
        <div class="nxw" id="lwWheel" data-logo="<?= e(logo_url()) ?>"></div>
        <div class="rf-admin-side">
            <p class="rf-wheel-note" id="lwNote"></p>
            <button type="button" class="lw-spin" id="lwSpin"><span class="lw-spin-glow" aria-hidden="true"></span><span class="lw-spin-ic">🎡</span><span class="lw-spin-t" id="lwSpinT">بچرخون!</span></button>
            <div class="lw-last" id="lwLast"></div>
            <div class="lw-items" id="lwItems"></div>
        </div>
    </div>
</div>

<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>🧾 تاریخچه‌ی چرخش‌ها</h3>
                <?php if (array_filter($hist, fn($h) => $h['src'] === 'admin')): ?><form method="post" data-confirm="چرخش‌های خودت (پنل) پاک بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="clear-admin"><button class="abtn abtn-sm abtn-ghost">پاک کردن چرخش‌های من</button></form><?php endif; ?></div>
            <?php if (!$hist): ?><div class="empty-state"><span>🎡</span>هنوز کسی نچرخونده.</div><?php else: ?>
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th>کی</th><th>نتیجه</th><th>زمان</th><th></th></tr></thead><tbody>
            <?php foreach ($hist as $h): ?>
                <tr>
                    <td><?php if ($h['member_id']): ?><b><?= e($h['first_name'] . ' ' . $h['last_name']) ?></b><small class="muted ltr m-phone"><?= e($h['phone']) ?></small><?php else: ?><span class="badge">🔒 خودم (پنل)</span><?php endif; ?></td>
                    <td><?= ['prize' => '🎁', 'empty' => '😅', 'again' => '🔁'][$h['kind']] ?? '' ?> <b><?= e($h['label']) ?></b><?= $h['src'] === 'daily' ? ' <small class="muted">(روزانه)</small>' : '' ?></td>
                    <td class="small muted"><?= e(jago($h['created_at'])) ?></td>
                    <td><?php if ($h['member_id'] && $h['kind'] === 'prize'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="deliver"><input type="hidden" name="spin" value="<?= (int) $h['id'] ?>"><button class="badge <?= $h['status'] === 'delivered' ? 'b-ok' : 'b-warn' ?>" style="cursor:pointer;border:0"><?= $h['status'] === 'delivered' ? '✅ تحویل شد' : '📦 تحویل بده' ?></button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <?php if (!$private): ?>
        <div class="card">
            <div class="card-head"><h3>🎁 هدیه دادن چرخش</h3></div>
            <form method="post" class="lw-grant"><?= csrf_field() ?><input type="hidden" name="act" value="grant">
                <?= f_text('who', 'کد عضویت، موبایل یا نام کاربری', '', ['ltr' => true, 'placeholder' => 'NX-123456', 'required' => true]) ?>
                <div class="grid g2"><?= f_text('spins', 'چند چرخش', '1', ['type' => 'number', 'min' => 1, 'max' => 100]) ?><?= f_text('note', 'یادداشت (اختیاری)', '', ['placeholder' => 'مثلاً جایزه‌ی مسابقه']) ?></div>
                <button class="rf-save" type="submit"><span class="rf-save-check">🎁</span><span>هدیه بده</span></button>
            </form>
            <?php if ($grants): ?><div class="lw-glist"><?php foreach ($grants as $g): ?><div><b><?= e($g['first_name'] . ' ' . $g['last_name']) ?></b><span>+<?= fa_num((string) $g['spins']) ?></span><small><?= e($g['note']) ?> · <?= e(jago($g['created_at'])) ?></small></div><?php endforeach; ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>window.NX_WHEEL = <?= json_encode($st + ['spinUrl' => 'wheel-view.php?id=' . $id, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php adm_foot(['wheel.js', 'luckwheel.js']);
