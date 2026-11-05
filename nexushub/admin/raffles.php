<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
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
adm_head('قرعه‌کشی‌ها', 'raffles');
?>
<div class="card">
    <div class="card-head"><h3>🎰 قرعه‌کشی‌ها</h3><a class="abtn abtn-primary abtn-sm" href="raffle-edit.php"><?= adm_icon('plus', 17) ?> قرعه‌کشی جدید</a></div>
    <?php if (!$list): ?><div class="empty-state"><span>🎰</span>هنوز قرعه‌کشی‌ای نساختی. «قرعه‌کشی جدید» رو بزن.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>عنوان</th><th>روش</th><th>وضعیت</th><th>زمان برگزاری</th><th>شرکت‌کننده</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $r): $st = raffle_stats($r); ?>
            <tr>
                <td><a class="t-title" href="raffle-view.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a><small class="muted"><?= e(raffle_prizes($r)[0]['title']) ?></small></td>
                <td><span class="badge"><?= e(raffle_modes()[$r['mode']][2] . ' ' . raffle_modes()[$r['mode']][0]) ?></span></td>
                <td><span class="badge <?= $badges[$r['status']] ?? '' ?>"><?= e(raffle_status_names()[$r['status']] ?? $r['status']) ?></span><?php if ($st['pending']): ?> <span class="badge b-warn"><?= fa_num((string) $st['pending']) ?> فیش</span><?php endif; ?></td>
                <td class="small"><?= e(jdate('j F Y — H:i', strtotime($r['draw_at']))) ?></td>
                <td class="small"><?= fa_num((string) $st['participants']) ?><?= $st['need'] ? ' / ' . fa_num((string) $st['need']) : '' ?><?= $r['mode'] === 'paid' ? '<br>🎟️ ' . fa_num((string) $st['sold']) . ($st['need_tickets'] ? ' / ' . fa_num((string) $st['need_tickets']) : '') : '' ?></td>
                <td class="actions">
                    <a class="abtn abtn-sm" href="raffle-view.php?id=<?= (int) $r['id'] ?>">مدیریت</a>
                    <a class="abtn abtn-sm abtn-ghost" href="raffle-edit.php?id=<?= (int) $r['id'] ?>"><?= adm_icon('edit', 15) ?></a>
                    <?php if ($r['status'] !== 'active'): ?><form method="post" style="display:inline" data-confirm="این قرعه‌کشی و همه‌ی شرکت‌کننده‌هاش حذف بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="abtn abtn-sm abtn-ghost" type="submit">✕</button></form><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php adm_foot();
