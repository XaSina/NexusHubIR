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
    if (($_POST['ajax'] ?? '') === 'draw') {   // 1st, 2nd, 3rd … — weighted, nobody twice
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_valid()) { echo json_encode(['ok' => false, 'error' => 'نشست منقضی شده؛ صفحه رو رفرش کن.']); exit; }
        $items = wheel_items($w); $pool = [];
        foreach ($items as $i => $it) if ($it['chance'] > 0 && !in_array($it['kind'], ['empty', 'again'], true)) $pool[$i] = $it;
        $n = max(1, min(count($pool), (int) ($_POST['n'] ?? 3)));
        if (!$pool) { echo json_encode(['ok' => false, 'error' => 'گزینه‌ای با شانس بیشتر از صفر نیست.']); exit; }
        $places = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم'];
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $i = wheel_pick($pool); if ($i < 0) break;
            $out[] = ['index' => $i, 'label' => $pool[$i]['label'], 'emoji' => $pool[$i]['emoji'], 'place' => $places[$k] ?? fa_num((string) ($k + 1))];
            insert('wheel_spins', ['wheel_id' => $id, 'member_id' => null, 'item' => $i, 'label' => 'نفر ' . ($places[$k] ?? ($k + 1)) . ': ' . $pool[$i]['label'], 'kind' => 'prize', 'src' => 'admin', 'status' => 'done', 'created_at' => now()]);
            unset($pool[$i]);
        }
        echo json_encode(['ok' => true, 'places' => $out], JSON_UNESCAPED_UNICODE); exit;
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
            $pz = row('SELECT * FROM prizes WHERE spin_id = ?', [(int) ($_POST['spin'] ?? 0)]);
            if ($pz) prize_set_status((int) $pz['id'], $pz['status'] === 'delivered' ? 'pending' : 'delivered');
            break;
        case 'clear-admin':
            q("DELETE FROM wheel_spins WHERE wheel_id = ? AND src = 'admin'", [$id]);
            flash('چرخش‌های خودت پاک شد.');
            break;
    }
    redirect('admin/wheel-view.php?id=' . $id . (isset($_GET['p']) ? '&p=' . (int) $_GET['p'] : ''));
}
prizes_expire_due();

$st = wheel_state($w, null, true);
$tot = (int) val('SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL', [$id]);
$won = (int) val("SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL AND kind = 'prize'", [$id]);
$todo = (int) val("SELECT COUNT(*) FROM prizes WHERE source = 'wheel' AND source_id = ? AND status = 'pending'", [$id]);
$people = (int) val('SELECT COUNT(DISTINCT member_id) FROM wheel_spins WHERE wheel_id = ? AND member_id IS NOT NULL', [$id]);
$per = 9;
$histN = (int) val('SELECT COUNT(*) FROM wheel_spins WHERE wheel_id = ?', [$id]);
$hPages = max(1, (int) ceil($histN / $per)); $hPage = max(1, min($hPages, (int) ($_GET['p'] ?? 1)));
$hist = rows('SELECT s.*, m.first_name, m.last_name, m.phone, m.member_code, p.status pstatus, p.prize_type, p.deadline, c.code coupon_code FROM wheel_spins s LEFT JOIN members m ON m.id = s.member_id
    LEFT JOIN prizes p ON p.spin_id = s.id LEFT JOIN coupons c ON c.id = p.coupon_id WHERE s.wheel_id = ? ORDER BY s.id DESC LIMIT ' . $per . ' OFFSET ' . (($hPage - 1) * $per), [$id]);
$hasAdmin = (bool) val("SELECT 1 FROM wheel_spins WHERE wheel_id = ? AND src = 'admin' LIMIT 1", [$id]);
$private = $w['audience'] === 'private';
$badge = ['draft' => ['b-muted', 'پیش‌نویس'], 'active' => ['b-ok', 'فعال'], 'paused' => ['b-warn', 'متوقف']][$w['status']] ?? ['', $w['status']];
adm_head($w['title'], 'wheels');
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/luck.css')) ?>">
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🎡</span><div><h2><?= e($w['title']) ?> <?php if ($private): ?><span class="badge">🔒 فقط برای خودم</span><?php else: ?><span class="badge <?= $badge[0] ?>"><?= e($badge[1]) ?></span><?php endif; ?></h2>
        <small><?= $private ? 'گردونه‌ی شخصی — فقط اینجا می‌چرخه' : fa_num((string) $tot) . ' چرخش · ' . fa_num((string) $people) . ' نفر · ' . fa_num((string) $won) . ' جایزه' ?></small></div></div>
    <div class="rfl-tools">
        <?php if (!$private): ?><a class="rfl-hbtn" href="<?= e(wheel_url($w)) ?>" target="_blank"><span><?= icon('external', 15) ?></span> صفحه روی سایت</a><?php endif; ?>
        <a class="rfl-hbtn" href="wheel-edit.php?id=<?= $id ?>"><span><?= adm_icon('edit', 15) ?></span> ویرایش</a>
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
            <?php if ($private): ?>
            <div class="lw-places" id="lwPlaces">
                <b>🏅 قرعه‌ی چند نفره (نفر اول، دوم، سوم …)</b>
                <small>گردونه برای هر رتبه جدا می‌چرخه و کسی دو بار انتخاب نمی‌شه.</small>
                <div class="lw-places-row"><div class="rf-quick lw-pn"><?php foreach ([2, 3, 4, 5, 10] as $n): ?><button type="button" data-n="<?= $n ?>"<?= $n === 3 ? ' class="on"' : '' ?>><?= fa_num((string) $n) ?> نفر</button><?php endforeach; ?></div>
                    <button type="button" class="rfl-hbtn is-warn" id="lwDraw"><span>🏅</span> شروع قرعه</button></div>
                <div class="lw-podium" id="lwPodium"></div>
            </div>
            <?php endif; ?>
            <div class="lw-last" id="lwLast"></div>
            <div class="lw-items" id="lwItems"></div>
        </div>
    </div>
</div>

<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>🧾 تاریخچه‌ی چرخش‌ها <small class="muted">(<?= fa_num((string) $histN) ?>)</small></h3>
                <?php if ($hasAdmin): ?><form method="post" data-confirm="چرخش‌های خودت (پنل) پاک بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="clear-admin"><button class="abtn abtn-sm abtn-ghost">پاک کردن چرخش‌های من</button></form><?php endif; ?></div>
            <?php if (!$hist): ?><div class="empty-state"><span>🎡</span>هنوز کسی نچرخونده.</div><?php else: ?>
            <div class="lwh-list">
            <?php foreach ($hist as $h): $ic = $h['prize_type'] === 'discount' ? '🏷️' : (['prize' => '🎁', 'empty' => '😅', 'again' => '🔁'][$h['kind']] ?? '•'); ?>
                <div class="lwh">
                    <div class="lwh-who"><?php if ($h['member_id']): ?><b><?= e($h['first_name'] . ' ' . $h['last_name']) ?></b><span class="pzx-meta"><span dir="ltr"><?= e($h['phone']) ?></span></span><?php else: ?><span class="badge">🔒 خودم (پنل)</span><?php endif; ?></div>
                    <div class="lwh-res"><span><?= $ic ?></span><b><?= e($h['label']) ?></b><?= $h['src'] === 'daily' ? '<small class="muted">روزانه</small>' : '' ?><?= $h['coupon_code'] ? '<code dir="ltr">' . e($h['coupon_code']) . '</code>' : '' ?></div>
                    <small class="lwh-when"><?= e(jago($h['created_at'])) ?></small>
                    <div class="lwh-st"><?php if ($h['pstatus']): $ps = prize_status_names()[$h['pstatus']] ?? ['•', $h['pstatus']]; ?>
                        <?php if ($h['prize_type'] === 'item' && in_array($h['pstatus'], ['pending', 'delivered', 'expired'], true)): ?><form method="post" action="?id=<?= $id ?>&p=<?= $hPage ?>"><?= csrf_field() ?><input type="hidden" name="act" value="deliver"><input type="hidden" name="spin" value="<?= (int) $h['id'] ?>"><button class="badge <?= $h['pstatus'] === 'delivered' ? 'b-ok' : ($h['pstatus'] === 'expired' ? 'b-muted' : 'b-warn') ?>" style="cursor:pointer;border:0" title="<?= $h['pstatus'] === 'delivered' ? 'برگردون' : 'تحویل داده شد' ?>"><?= $h['pstatus'] === 'delivered' ? '✅ تحویل شد' : ($h['pstatus'] === 'expired' ? '⌛ باطل شد' : '📦 تحویل داده شد') ?></button></form>
                        <?php else: ?><span class="badge"><?= $ps[0] ?> <?= e($ps[1]) ?></span><?php endif; ?>
                        <?php if ($h['pstatus'] === 'pending' && $h['deadline']): ?><small class="lwh-dl">⏳ <?= e(raffle_left_text(max(60, strtotime($h['deadline']) - time()))) ?></small><?php endif; ?>
                    <?php endif; ?></div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php if ($hPages > 1): ?><div class="pzx-pager"><?php for ($i = 1; $i <= $hPages; $i++): ?><a class="<?= $i === $hPage ? 'on' : '' ?>" href="?id=<?= $id ?>&p=<?= $i ?>"><?= fa_num((string) $i) ?></a><?php endfor; ?></div><?php endif; ?>
            <p class="small muted" style="margin:10px 0 0">پیام دادن به برنده‌ها و همه‌ی جایزه‌ها: <a href="prizes.php?t=wheel">🎁 جایزه‌ها و برنده‌ها</a></p>
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
        </div>
        <?php endif; ?>
    </div>
</div>
<script>window.NX_WHEEL = <?= json_encode($st + ['spinUrl' => 'wheel-view.php?id=' . $id, 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php adm_foot(['wheel.js', 'luckwheel.js']);
