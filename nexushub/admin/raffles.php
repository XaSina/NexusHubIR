<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (prize_claim_save()) redirect('admin/raffles.php');
    if (($_POST['act'] ?? '') === 'opts') {
        save_settings(['footer_raffle_link' => !empty($_POST['footer_raffle_link']) ? '1' : '0',
            'raffle_ended_access' => in_array($_POST['raffle_ended_access'] ?? '', ['all', 'participants', 'winners'], true) ? $_POST['raffle_ended_access'] : 'winners']);
        flash('تنظیمات ذخیره شد ✅');
        redirect('admin/raffles.php');
    }
    if (($_POST['act'] ?? '') === 'delete') {
        $r = row('SELECT * FROM raffles WHERE id = ?', [$id]);
        if ($r && in_array($r['status'], ['draft', 'cancelled', 'failed', 'drawn'], true)) {
            q('DELETE FROM raffle_entries WHERE raffle_id = ?', [$id]);
            q('DELETE FROM raffle_requests WHERE raffle_id = ?', [$id]);
            q('DELETE FROM raffles WHERE id = ?', [$id]);
            flash('قرعه‌کشی حذف شد.');
        } else flash('قرعه‌کشی در حال برگزاری رو اول لغو کن.', 'error');
    }
    redirect('admin/raffles.php');
}

raffles_due();
$list = rows('SELECT * FROM raffles ORDER BY (status = "active") DESC, draw_at DESC, id DESC');
$badges = ['draft' => 'b-muted', 'active' => 'b-ok', 'drawn' => 'b-info', 'failed' => 'b-warn', 'cancelled' => 'b-muted'];
$cnt = ['active' => 0, 'drawn' => 0, 'people' => 0];
foreach ($list as $x) { if (isset($cnt[$x['status']])) $cnt[$x['status']]++; }
adm_head('قرعه‌کشی‌ها', 'raffles');
?>
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🎰</span><div><h2>قرعه‌کشی‌ها</h2><small><?= fa_num((string) count($list)) ?> قرعه‌کشی · <?= fa_num((string) $cnt['active']) ?> فعال · <?= fa_num((string) $cnt['drawn']) ?> برگزار شده</small></div></div>
    <a class="rfl-new" href="raffle-edit.php"><span><?= adm_icon('plus', 18) ?></span> قرعه‌کشی جدید</a>
</div>
<form method="post" class="rfl-opts" id="rflOpts"><?= csrf_field() ?><input type="hidden" name="act" value="opts">
    <label class="rfl-opt"><span class="rfl-sw"><input type="checkbox" name="footer_raffle_link" value="1" <?= setting_on('footer_raffle_link') ? 'checked' : '' ?>><i></i></span> لینک «قرعه‌کشی» توی فوتر سایت</label>
    <label class="rfl-opt">صفحه‌ی قرعه‌کشی‌های تموم‌شده (بعد از زمان نمایش نتیجه) باز باشه برای:
        <select name="raffle_ended_access"><?php foreach (['winners' => '🏆 فقط برنده‌ها', 'participants' => '👥 فقط شرکت‌کننده‌ها', 'all' => '🌍 همه'] as $k => $t): ?><option value="<?= $k ?>" <?= setting('raffle_ended_access', 'winners') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
</form>
<script>document.addEventListener('change', function (e) { if (e.target.closest('#rflOpts')) e.target.closest('form').submit(); });</script>
<?= prize_claim_card() ?>
<?php if (!$list): ?>
    <div class="card"><div class="empty-state"><span>🎰</span>هنوز قرعه‌کشی‌ای نساختی. «قرعه‌کشی جدید» رو بزن.</div></div>
<?php else: ?>
<div class="rfl-grid">
    <?php foreach ($list as $i => $r): $st = raffle_stats($r); $pz = raffle_prizes($r); $paid = $r['mode'] === 'paid';
        $have = $paid ? $st['sold'] : $st['participants']; $need = $paid ? $st['need_tickets'] : $st['need']; $pct = $need ? min(100, round($have / max(1, $need) * 100)) : 100;
        $res = json_decode((string) ($r['results'] ?? ''), true) ?: []; ?>
    <article class="rfl-card rfl-<?= e($r['status']) ?>" style="--i:<?= $i ?>">
        <a class="rfl-top" href="raffle-view.php?id=<?= (int) $r['id'] ?>">
            <span class="rfl-art"><?php if ($r['image']): ?><img src="<?= e(media_url($r['image'])) ?>" alt=""><?php else: ?><?= e($pz[0]['emoji'] ?? '🎁') ?><?php endif; ?></span>
            <span class="rfl-t"><b><?= e($r['title']) ?></b><small>🎁 <?= e($pz[0]['title']) ?><?= count($pz) > 1 ? ' + ' . fa_num((string) (count($pz) - 1)) : '' ?></small></span>
            <span class="badge <?= $badges[$r['status']] ?? '' ?>"><?= e(raffle_status_names()[$r['status']] ?? $r['status']) ?></span>
        </a>
        <div class="rfl-meta">
            <span><?= raffle_modes()[$r['mode']][2] ?> <?= e(raffle_modes()[$r['mode']][0]) ?></span>
            <span>⏰ <?= e(jdate('j F — H:i', strtotime($r['draw_at']))) ?></span>
            <?php if ($st['pending']): ?><span class="rfl-warn">🧾 <?= fa_num((string) $st['pending']) ?> فیش منتظر</span><?php endif; ?>
        </div>
        <?php if ($r['status'] === 'drawn' && $res): ?>
            <div class="rfl-win">🏆 <b><?= e(raffle_member_full((int) $res[0]['member_id'], (string) ($res[0]['full'] ?? $res[0]['name']))) ?></b><?= count($res) > 1 ? ' و ' . fa_num((string) (count($res) - 1)) . ' نفر دیگه' : '' ?></div>
        <?php else: ?>
            <div class="rfl-prog"><span class="rfl-bar"><i style="width:<?= $pct ?>%"></i></span><small><?= $paid ? '🎟️' : '👥' ?> <?= fa_num((string) $have) ?><?= $need ? ' / ' . fa_num((string) $need) : '' ?></small></div>
        <?php endif; ?>
        <div class="rfl-act">
            <a class="rfl-go" href="raffle-view.php?id=<?= (int) $r['id'] ?>">🎡 مدیریت و گردونه</a>
            <a class="rfl-ib" href="raffle-edit.php?id=<?= (int) $r['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
            <?php if ($r['status'] !== 'active'): ?><form method="post" data-confirm="این قرعه‌کشی و همه‌ی شرکت‌کننده‌هاش حذف بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="rfl-ib rfl-del" type="submit" title="حذف"><?= adm_icon('trash', 16) ?></button></form><?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php adm_foot();
