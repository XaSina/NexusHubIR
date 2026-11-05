<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$id = (int) ($_GET['id'] ?? 0);
$r = row('SELECT * FROM raffles WHERE id = ?', [$id]);
if (!$r) { flash('قرعه‌کشی پیدا نشد.', 'error'); redirect('admin/raffles.php'); }

if (is_post()) {
    csrf_check();
    switch ($_POST['act'] ?? '') {
        case 'approve': case 'reject':
            $res = raffle_review_request((int) ($_POST['req'] ?? 0), $_POST['act'] === 'approve', mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 200));
            flash($res['ok'] ? ($_POST['act'] === 'approve' ? 'تأیید شد؛ تیکت‌ها: ' . fa_num($res['from'] . ' تا ' . $res['to']) : 'رد شد.') : $res['error'], $res['ok'] ? 'success' : 'error');
            break;
        case 'draw':
            if ($r['status'] !== 'active') { flash('فقط قرعه‌کشی فعال برگزار می‌شه.', 'error'); break; }
            $r = raffle_maybe_draw($r, true);
            flash($r['status'] === 'drawn' ? 'قرعه‌کشی برگزار شد 🏆' : 'به حد نصاب نرسید و برگزار نشد.', $r['status'] === 'drawn' ? 'success' : 'warn');
            break;
        case 'activate':
            if ($r['status'] === 'draft' && strtotime($r['draw_at']) > time()) { q("UPDATE raffles SET status = 'active', updated_at = ? WHERE id = ?", [now(), $id]); flash('فعال شد 🎰'); }
            else flash('زمان برگزاری گذشته؛ اول ویرایشش کن.', 'error');
            break;
        case 'cancel':
            if (in_array($r['status'], ['draft', 'active'], true)) { q("UPDATE raffles SET status = 'cancelled', updated_at = ? WHERE id = ?", [now(), $id]); flash('لغو شد.'); }
            break;
    }
    redirect('admin/raffle-view.php?id=' . $id);
}

$r = raffle_maybe_draw($r);
$st = raffle_stats($r);
$results = json_decode((string) ($r['results'] ?? ''), true) ?: [];
$people = rows('SELECT e.*, m.first_name, m.last_name, m.phone, m.member_code FROM raffle_entries e JOIN members m ON m.id = e.member_id WHERE e.raffle_id = ? ORDER BY e.id DESC LIMIT 500', [$id]);
$reqs = $r['mode'] === 'paid' ? rows('SELECT q.*, m.first_name, m.last_name, m.phone FROM raffle_requests q JOIN members m ON m.id = q.member_id WHERE q.raffle_id = ? ORDER BY q.status = "pending" DESC, q.id DESC LIMIT 300', [$id]) : [];
$badges = ['draft' => 'b-muted', 'active' => 'b-ok', 'drawn' => 'b-info', 'failed' => 'b-warn', 'cancelled' => 'b-muted'];
adm_head($r['title'], 'raffles');
?>
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic"><?= e(raffle_prizes($r)[0]['emoji'] ?? '🎰') ?></span><div><h2><?= e($r['title']) ?> <span class="badge <?= $badges[$r['status']] ?? '' ?>"><?= e(raffle_status_names()[$r['status']]) ?></span></h2><small><?= raffle_modes()[$r['mode']][2] ?> <?= e(raffle_modes()[$r['mode']][0]) ?> · <?= e(jdate('j F Y — H:i', strtotime($r['draw_at']))) ?></small></div></div>
    <div class="rfl-tools">
        <a class="rfl-hbtn" href="<?= e(raffle_url($r)) ?>" target="_blank"><span><?= icon('external', 15) ?></span> صفحه‌ی قرعه‌کشی</a>
        <a class="rfl-hbtn" href="raffle-edit.php?id=<?= $id ?>"><span><?= adm_icon('edit', 15) ?></span> ویرایش</a>
        <?php if ($r['status'] === 'draft'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="activate"><button class="rfl-hbtn is-go"><span>▶</span> فعال کن</button></form><?php endif; ?>
        <?php if ($r['status'] === 'active'): ?><form method="post" data-confirm="همین الان برگزار بشه؟ (اگه به حد نصاب نرسیده باشه برگزار نمی‌شه)"><?= csrf_field() ?><input type="hidden" name="act" value="draw"><button class="rfl-hbtn is-warn"><span>🏆</span> برگزاری همین الان</button></form><?php endif; ?>
        <?php if (in_array($r['status'], ['draft', 'active'], true)): ?><form method="post" data-confirm="قرعه‌کشی لغو بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="cancel"><button class="rfl-hbtn is-dim"><span>✕</span> لغو</button></form><?php endif; ?>
    </div>
</div>
<div class="card mb">
    <div class="grid g4 stats-grid">
        <div class="stat"><span class="stat-ic">👥</span><div><b><?= fa_num((string) $st['participants']) ?></b><small>شرکت‌کننده<?= $st['need'] ? ' (حداقل ' . fa_num((string) $st['need']) . ')' : '' ?></small></div></div>
        <?php if ($r['mode'] === 'paid'): ?><div class="stat"><span class="stat-ic">🎟️</span><div><b><?= fa_num((string) $st['sold']) ?></b><small>تیکت فروخته‌شده<?= $st['need_tickets'] ? ' (حداقل ' . fa_num((string) $st['need_tickets']) . ')' : '' ?></small></div></div>
        <div class="stat"><span class="stat-ic">💳</span><div><b><?= money($st['sold'] * (int) $r['ticket_price']) ?></b><small>تومان فروش تأییدشده</small></div></div><?php endif; ?>
        <div class="stat"><span class="stat-ic">⏰</span><div><b style="font-size:1rem"><?= e(jdate('j F — H:i', strtotime($r['draw_at']))) ?></b><small><?= $st['left'] ? e(raffle_left_text($st['left'])) . ' مونده' : 'زمان برگزاری' ?></small></div></div>
        <div class="stat"><span class="stat-ic"><?= $st['ready'] ? '✅' : '⏳' ?></span><div><b style="font-size:1rem"><?= $st['ready'] ? 'به حد نصاب رسیده' : 'هنوز به حد نصاب نرسیده' ?></b><small><?= e(raffle_modes()[$r['mode']][0]) ?></small></div></div>
    </div>
</div>

<?php $wst = raffle_state($r, null, true); ?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<div class="card mb rf-admin-card">
    <div class="card-head"><h3>🎡 گردونه‌ی قرعه‌کشی</h3><small class="muted">فقط برای تو: اسم کامل شرکت‌کننده‌ها روی گردونه‌ست</small></div>
    <div class="rf-admin-wheel">
        <div class="nxw" id="rfWheelWrap" data-logo="<?= e(logo_url()) ?>"></div>
        <div class="rf-admin-side">
            <p class="rf-wheel-note" id="rfWheelNote"></p>
            <button type="button" class="rf-replay" id="rfReplay"><span class="rf-replay-ic">▶</span> چرخوندن گردونه</button>
            <div class="rf-results" id="rfResults"></div>
        </div>
    </div>
</div>
<script>window.NX_RAFFLE = <?= json_encode($wst + ['admin' => true, 'logged' => false, 'prizes' => raffle_prizes($r), 'drawnAt' => $r['drawn_at'] ? strtotime($r['drawn_at']) : 0, 'now' => time(), 'show' => false], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>

<?php if ($results): ?>
<div class="card mb"><div class="card-head"><h3>🏆 برنده‌ها</h3><small class="muted"><?= e(jdate('j F Y — H:i', strtotime((string) $r['drawn_at']))) ?></small></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>جایزه</th><th>برنده</th><th>موبایل</th><?= $r['mode'] === 'paid' ? '<th>تیکت برنده</th>' : '' ?><th>شانسش</th></tr></thead><tbody>
    <?php foreach ($results as $i => $w): $wm = row('SELECT first_name, last_name, phone, member_code FROM members WHERE id = ?', [$w['member_id']]); ?>
        <tr><td><?= ['🥇', '🥈', '🥉'][$i] ?? '🏅' ?> <?= e($w['prize']) ?></td><td><b><?= e(($wm['first_name'] ?? '') . ' ' . ($wm['last_name'] ?? '')) ?></b> <small class="muted ltr"><?= e($wm['member_code'] ?? '') ?></small></td><td class="ltr small m-phone"><?= e($wm['phone'] ?? '') ?></td><?= $r['mode'] === 'paid' ? '<td><b>#' . fa_num((string) $w['ticket']) . '</b></td>' : '' ?><td><?= fa_num((string) $w['chance']) ?>٪</td></tr>
    <?php endforeach; ?></tbody></table></div></div>
<?php elseif ($r['status'] === 'failed'): ?>
<div class="alert alert-warn mb">این قرعه‌کشی به حد نصاب نرسید و برگزار نشد.<?= $r['mode'] === 'paid' && $st['sold'] ? ' پول خریدارهای تیکت (لیست پایین) رو برگردون.' : '' ?></div>
<?php endif; ?>

<?php if ($r['mode'] === 'paid'): ?>
<div class="card mb"><div class="card-head"><h3>🧾 خریدهای تیکت</h3><small class="muted">کارت مقصد: <?= e(setting('shop_card_number')) ?> — <?= e(setting('shop_card_name')) ?></small></div>
    <?php if (!$reqs): ?><div class="empty-state"><span>🧾</span>هنوز کسی تیکت نخریده.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>خریدار</th><th>تعداد</th><th>مبلغ</th><th>فیش</th><th>وضعیت</th><th></th></tr></thead><tbody>
    <?php foreach ($reqs as $q): ?>
        <tr>
            <td><b><?= e($q['first_name'] . ' ' . $q['last_name']) ?></b><small class="muted ltr m-phone"><?= e($q['phone']) ?></small></td>
            <td><?= fa_num((string) $q['qty']) ?> تیکت<?= $q['from_no'] ? '<br><small class="muted">#' . fa_num($q['from_no'] . ' تا #' . $q['to_no']) . '</small>' : '' ?></td>
            <td><?= money((int) $q['amount']) ?></td>
            <td><?php if ($q['receipt']): ?><a href="<?= e(url('raffle/' . rawurlencode($r['slug']) . '/receipt/' . $q['id'])) ?>" target="_blank"><img class="thumb" src="<?= e(url('raffle/' . rawurlencode($r['slug']) . '/receipt/' . $q['id'])) ?>" alt="فیش" loading="lazy"></a><?php endif; ?></td>
            <td><?= ['pending' => '<span class="badge b-warn">در انتظار بررسی</span>', 'approved' => '<span class="badge b-ok">تأیید شد</span>', 'rejected' => '<span class="badge b-muted">رد شد</span>'][$q['status']] ?? e($q['status']) ?><?= $q['note'] ? '<br><small class="muted">' . e($q['note']) . '</small>' : '' ?></td>
            <td class="actions"><?php if ($q['status'] === 'pending'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="req" value="<?= (int) $q['id'] ?>"><input type="hidden" name="act" value="approve"><button class="abtn abtn-sm abtn-primary">تأیید</button></form>
                <form method="post" data-confirm="این خرید رد بشه؟"><?= csrf_field() ?><input type="hidden" name="req" value="<?= (int) $q['id'] ?>"><input type="hidden" name="act" value="reject"><input type="hidden" name="note" value="فیش پرداخت تأیید نشد."><button class="abtn abtn-sm abtn-ghost">رد</button></form>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card"><div class="card-head"><h3>👥 شرکت‌کننده‌ها</h3><small class="muted"><?= fa_num((string) count($people)) ?> نفر</small></div>
    <?php if (!$people): ?><div class="empty-state"><span>👥</span>هنوز کسی شرکت نکرده.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>نام</th><th>موبایل</th><th><?= $r['mode'] === 'paid' ? 'تیکت' : ($r['mode'] === 'referral' ? 'دعوت‌ها' : '') ?></th><th>شانس</th><th>زمان</th></tr></thead><tbody>
    <?php foreach ($people as $p): $pp = $st['pool'][(int) $p['member_id']] ?? null; ?>
        <tr><td><b><?= e($p['first_name'] . ' ' . $p['last_name']) ?></b> <small class="muted ltr"><?= e($p['member_code']) ?></small></td><td class="ltr small m-phone"><?= e($p['phone']) ?></td>
            <td><?= $r['mode'] === 'paid' ? fa_num((string) ($pp['tickets'] ?? 0)) : ($r['mode'] === 'referral' ? fa_num((string) $p['bonus']) : '') ?></td><td><?= $pp ? fa_num((string) $pp['chance']) . '٪' : '—' ?></td><td class="small muted"><?= e(jago($p['created_at'])) ?></td></tr>
    <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php adm_foot(['wheel.js', 'raffle.js']);
