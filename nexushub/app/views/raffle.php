<?php
/** @var array $raffle */
$r = $raffle;
$me = current_member();
$state = raffle_state($r, $me);
$prizes = raffle_prizes($r);
$mode = $r['mode'];
$modeInfo = raffle_modes()[$mode];
$open = raffle_open($r);
$medals = ['🥇', '🥈', '🥉'];
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/raffle.css')) ?>">
<section class="container rf-page" id="rfPage" data-id="<?= (int) $r['id'] ?>">

    <div class="rf-hero rf-in" style="<?= $r['image'] ? '--img:url(\'' . e(media_url($r['image'])) . '\')' : '' ?>">
        <div class="rf-hero-bg" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="rf-hero-in">
            <?php if ($r['image']): ?><div class="rf-hero-art"><img src="<?= e(media_url($r['image'])) ?>" alt="<?= e($r['title']) ?>"></div><?php endif; ?>
            <span class="rf-chip rf-st-<?= e($r['status']) ?>" id="rfStatus"><?= e(raffle_status_names()[$r['status']]) ?></span>
            <h1 class="rf-title"><?= e($r['title']) ?></h1>
            <p class="rf-mode-line"><?= $modeInfo[2] ?> <?= e($modeInfo[0]) ?> · <?= e($modeInfo[1]) ?></p>
            <div class="rf-count" id="rfCount" data-left="<?= (int) $state['left'] ?>" <?= $r['status'] !== 'active' ? 'hidden' : '' ?>>
                <div><b data-u="d">۰</b><small>روز</small></div><div><b data-u="h">۰</b><small>ساعت</small></div><div><b data-u="m">۰</b><small>دقیقه</small></div><div><b data-u="s">۰</b><small>ثانیه</small></div>
            </div>
            <p class="rf-when"><?= icon('calendar', 16) ?> <?= e(jdate('l j F Y — ساعت H:i', strtotime($r['draw_at']))) ?></p>
            <div class="rf-progress" id="rfProg"></div>
        </div>
    </div>

    <div class="rf-prizes">
        <?php foreach ($prizes as $i => $p): ?>
        <div class="rf-prize rf-in" style="--i:<?= $i ?>;--d:<?= $i + 2 ?>"><span class="rf-medal"><?= $medals[$i] ?? '🏅' ?></span><span class="rf-pz-emoji"><?= e($p['emoji'] ?? '🎁') ?></span><b><?= e($p['title']) ?></b><small>جایزه‌ی نفر <?= e(['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم'][$i] ?? fa_num((string) ($i + 1))) ?></small></div>
        <?php endforeach; ?>
    </div>

    <div class="rf-grid">
        <div class="rf-main">
            <div class="rf-wheel-card glass rf-in" style="--d:3">
                <div class="rf-wheel-head"><b>🎡 گردونه‌ی قرعه‌کشی</b><span id="rfLive" class="rf-live" hidden><i></i> زنده</span></div>
                <div class="nxw" id="rfWheelWrap" data-logo="<?= e(logo_url()) ?>"></div>
                <p class="rf-wheel-note" id="rfWheelNote"></p>
                <div class="rf-results" id="rfResults"></div>
                <button type="button" class="rf-replay" id="rfReplay" hidden><span class="rf-replay-ic">▶</span> پخش دوباره‌ی قرعه‌کشی</button>
            </div>
            <?php if (trim((string) $r['description']) !== ''): ?>
            <div class="rf-desc glass"><h3><?= icon('info', 18) ?> توضیحات و قوانین</h3><div class="prose"><?= nl2br(e($r['description'])) ?></div></div>
            <?php endif; ?>
        </div>

        <aside class="rf-side">
            <div class="rf-act glass rf-in" id="rfAct" style="--d:4">
                <?php if (!$me && $open): ?>
                    <h3>شرکت در قرعه‌کشی</h3>
                    <p class="muted">برای شرکت باید عضو سایت باشی. ثبت‌نام کمتر از یک دقیقه طول می‌کشه.</p>
                    <button type="button" class="btn btn-primary btn-block rf-join" data-login><?= icon('user', 18) ?> ورود / ثبت‌نام و شرکت</button>
                <?php elseif ($open && $mode !== 'paid'): ?>
                    <div id="rfJoinBox"></div>
                <?php elseif ($open && $mode === 'paid'): ?>
                    <h3>🎟️ خرید تیکت</h3>
                    <div class="rf-price"><span>قیمت هر تیکت</span><b><?= money((int) $r['ticket_price']) ?> <small>تومان</small></b></div>
                    <?php if ((int) $r['max_tickets_user']): ?><p class="small muted">هر نفر حداکثر <?= fa_num((string) $r['max_tickets_user']) ?> تیکت می‌تونه بخره.</p><?php endif; ?>
                    <div id="rfMyTickets"></div>
                    <form class="rf-buy" id="rfBuy" enctype="multipart/form-data">
                        <div class="rf-qty"><button type="button" data-q="-1">−</button><input type="number" name="qty" id="rfQty" value="1" min="1" inputmode="numeric"><button type="button" data-q="1">+</button></div>
                        <div class="rf-total">مبلغ قابل پرداخت: <b id="rfTotal"><?= money((int) $r['ticket_price']) ?></b> تومان</div>
                        <div class="rf-cardbox"><small>کارت به کارت به:</small><b dir="ltr" class="rf-cardno"><?= e(trim(chunk_split((string) setting('shop_card_number'), 4, ' '))) ?></b><small><?= e(setting('shop_card_name')) ?> — <?= e(setting('shop_card_bank')) ?></small><button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e((string) setting('shop_card_number')) ?>"><?= icon('copy', 15) ?> کپی شماره کارت</button></div>
                        <label class="rf-file"><input type="file" name="receipt" accept="image/*" required><span>📎 <em>انتخاب عکس فیش واریزی</em></span></label>
                        <button class="btn btn-primary btn-block" type="submit">ثبت خرید و ارسال فیش</button>
                        <small class="muted">بعد از تأیید فیش، شماره‌ی تیکت‌هات اینجا و توی اعلان‌ها نشون داده می‌شه.</small>
                    </form>
                <?php else: ?>
                    <h3><?= $r['status'] === 'drawn' ? '🏆 قرعه‌کشی برگزار شد' : ($r['status'] === 'failed' ? 'ℹ️ قرعه‌کشی برگزار نشد' : ($r['status'] === 'cancelled' ? 'این قرعه‌کشی لغو شد' : '⏰ ثبت‌نام بسته شد')) ?></h3>
                    <p class="muted"><?= $r['status'] === 'failed' ? 'تعداد شرکت‌کننده‌ها به حد نصاب نرسید.' : ($r['status'] === 'drawn' ? 'نتیجه رو کنار گردونه ببین.' : 'چند لحظه دیگه گردونه می‌چرخه…') ?></p>
                    <?php if ($r['status'] === 'drawn' && $me && $r['drawn_at']) foreach (json_decode((string) $r['results'], true) ?: [] as $w) if ((int) $w['member_id'] === (int) $me['id']) echo prize_claim_box((string) $r['drawn_at'], (string) $w['prize']); ?>
                <?php endif; ?>
                <div id="rfMine"></div>
            </div>

            <div class="rf-how glass rf-in" style="--d:5">
                <h3>📜 روش قرعه‌کشی</h3>
                <ul>
                    <?php if ($mode === 'free'): ?>
                        <li>هر عضو فقط یک بار شرکت می‌کنه و شانس همه برابره.</li>
                    <?php elseif ($mode === 'referral'): ?>
                        <li>با شرکت کردن یک شانس داری.</li>
                        <li>هر دوستی که با <b>لینک دعوت خودت</b> وارد بشه و شرکت کنه، <b><?= fa_num((string) (float) $r['ticket_bonus']) ?>٪</b> یک شانس کامل بهت اضافه می‌کنه.</li>
                        <li><?= (int) $r['max_referrals'] ? 'حداکثر ' . fa_num((string) $r['max_referrals']) . ' دعوت امتیاز داره.' : 'تعداد دعوت‌ها نامحدوده.' ?></li>
                    <?php else: ?>
                        <li>هر تیکت یک شماره داره (مثلاً اگه ۸ تا بخری شماره‌های ۱ تا ۸ مال توئه).</li>
                        <li>گردونه یک شماره رو انتخاب می‌کنه؛ صاحب اون تیکت برنده‌ست. تیکت بیشتر = شانس بیشتر.</li>
                        <?php if ((int) $r['min_tickets']): ?><li>باید حداقل <b><?= fa_num((string) $r['min_tickets']) ?></b> تیکت فروخته بشه.</li><?php endif; ?>
                    <?php endif; ?>
                    <li>اگه تا زمان برگزاری حداقل <b><?= fa_num((string) $r['min_participants']) ?></b> نفر شرکت نکنن، قرعه‌کشی برگزار نمی‌شه.</li>
                    <li>سر ساعت، گردونه همین‌جا زنده می‌چرخه و هر نفر فقط یک جایزه می‌بره.</li>
                    <?php if (prize_claim_on()): ?><li>🏆 برنده‌ها باید تا <b><?= fa_num((string) prize_claim_hours()) ?> ساعت</b> بعد از قرعه‌کشی <?= prize_claim_ids() ? 'به ' . implode(' یا ', array_map(fn($id, $l) => '<a href="' . e($l) . '" target="_blank" rel="noopener" dir="ltr">' . e($id) . '</a>', array_keys(prize_claim_ids()), prize_claim_ids())) : 'به پشتیبانی' ?> توی تلگرام پیام بدن؛ وگرنه جایزه باطل می‌شه.</li><?php endif; ?>
                </ul>
            </div>

            <div class="rf-people glass rf-in" style="--d:6">
                <h3>👥 شرکت‌کننده‌ها <small id="rfPeopleN"></small></h3>
                <div id="rfPeople" class="rf-plist"></div>
            </div>
        </aside>
    </div>
</section>
<script>window.NX_RAFFLE = <?= json_encode($state + ['price' => (int) $r['ticket_price'], 'cap' => (int) $r['max_tickets_user'], 'invite' => $me && $mode === 'referral' ? raffle_url($r) . '?ref=' . rawurlencode($me['member_code']) : '', 'logged' => (bool) $me, 'prizes' => $prizes, 'bonusPct' => (float) $r['ticket_bonus'], 'maxRef' => (int) $r['max_referrals'], 'drawnAt' => $r['drawn_at'] ? strtotime($r['drawn_at']) : 0, 'now' => time(), 'resultLive' => raffle_result_live($r), 'show' => !empty($_GET['show'])], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset('js/wheel.js')) ?>" defer></script>
<script src="<?= e(asset('js/raffle.js')) ?>" defer></script>
