<?php
require __DIR__ . '/inc/boot.php';
require_cap('shop');

if (is_post()) {
    csrf_check();
    if (($_POST['act'] ?? '') === 'rebuild' && can('*')) {
        $n = sales_close_months(true);
        flash('🔄 ' . fa_num((string) $n) . ' ماه بسته‌شده دوباره از روی سفارش‌ها حساب شد.');
    }
    redirect('admin/reports.php');
}

sales_close_months();
[$cy, $cm, $cd] = jmonth_now();
$curKey = jmonth_key($cy, $cm);

// which month is on the big view (default: the running month)
$sel = jmonth_parse((string) ($_GET['m'] ?? '')) ?: [$cy, $cm];
if (jmonth_key($sel[0], $sel[1]) > $curKey) $sel = [$cy, $cm];
[$sy, $sm] = $sel;
$selKey = jmonth_key($sy, $sm);
$isLive = $selKey === $curKey;
[$py, $pm] = jmonth_prev($sy, $sm);
$M = sales_month($sy, $sm);
$P = sales_month($py, $pm);

// every month from the first order up to now, newest first
$first = val('SELECT MIN(created_at) FROM orders');
$all = [];
if ($first) {
    $t = strtotime((string) $first);
    [$fy, $fm] = gregorian_to_jalali((int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
    $y = $fy; $m = $fm; $g = 0;
    while (jmonth_key($y, $m) <= $curKey && $g++ < 600) { $all[] = [$y, $m]; [$y, $m] = jmonth_next($y, $m); }
}
if (!$all) $all[] = [$cy, $cm];
$all = array_reverse($all);

// CSV export of every month
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report-' . $curKey . '.csv"');
    echo "\xEF\xBB\xBF" . "ماه,وضعیت,فروش (تومان),سفارش موفق,کل سفارش‌ها,لغو/منقضی,میانگین سفارش,مشتری,اقلام,تخفیف,عضو جدید,تغییر فروش نسبت به ماه قبل (%)\n";
    foreach ($all as $i => [$y, $m]) {
        $d = sales_month($y, $m); [$qy, $qm] = jmonth_prev($y, $m); $q = sales_month($qy, $qm);
        $dl = sales_delta($d['revenue'], $q['revenue']);
        echo implode(',', [jalali_month_names()[$m] . ' ' . $y, jmonth_key($y, $m) === $curKey ? 'در جریان' : 'بسته شد', $d['revenue'], $d['sold'], $d['orders'], $d['lost'], $d['avg'], $d['customers'], $d['items'], $d['discount'], $d['members'], $dl === null ? '' : $dl]) . "\n";
    }
    exit;
}

$per = 5;
$pages = max(1, (int) ceil(count($all) / $per));
$page = max(1, min($pages, (int) ($_GET['p'] ?? 1)));
$slice = array_slice($all, ($page - 1) * $per, $per);
$maxRev = 1;
foreach ($all as [$y, $m]) $maxRev = max($maxRev, (int) sales_month($y, $m)['revenue']);

$link = function (array $q) use ($selKey, $page): string {
    $q += ['m' => $selKey, 'p' => $page];
    return url('admin/reports.php') . '?' . http_build_query($q);
};
$chip = function (?float $d, bool $inverse = false): string {
    if ($d === null) return '<span class="srp-d is-new" title="ماه قبل صفر بود">✦ جدید</span>';
    if ($d == 0.0) return '<span class="srp-d is-flat">● بدون تغییر</span>';
    $up = $d > 0; $good = $inverse ? !$up : $up;
    return '<span class="srp-d ' . ($good ? 'is-up' : 'is-down') . '">' . ($up ? '▲' : '▼') . ' ' . fa_num(rtrim(rtrim(number_format(abs($d), 1), '0'), '.')) . '٪</span>';
};
$days = (int) $M['days'];
$elapsed = $isLive ? min($days, max(1, (int) $cd)) : $days;
$pace = $isLive && $elapsed > 0 ? (int) round($M['revenue'] / $elapsed * $days) : 0;

adm_head('گزارش فروش ماهانه', 'reports');
?>
<section class="srp-hero card">
    <div class="srp-hero-top">
        <div class="srp-hero-t">
            <span class="srp-hero-ic">📈</span>
            <div>
                <h2><?= e(jmonth_label($sy, $sm)) ?></h2>
                <?php if ($isLive): ?>
                    <span class="srp-state is-live"><i></i> در جریان · روز <?= fa_num((string) $elapsed) ?> از <?= fa_num((string) $days) ?></span>
                <?php else: ?>
                    <span class="srp-state">🔒 بسته شد<?= !empty($M['closed_at']) ? ' · ' . jdate('j F Y', $M['closed_at']) : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="srp-hero-act">
            <?php if (!$isLive): ?><a class="abtn abtn-ghost abtn-sm" href="<?= e(url('admin/reports.php')) ?>">⏱️ ماه جاری</a><?php endif; ?>
            <a class="abtn abtn-ghost abtn-sm" href="<?= e($link(['export' => 'csv'])) ?>">⬇️ خروجی اکسل</a>
        </div>
    </div>
    <div class="srp-month-bar" aria-hidden="true"><i style="width:<?= round($elapsed * 100 / max(1, $days)) ?>%"></i></div>
    <div class="srp-hero-main">
        <div class="srp-big">
            <small>فروش <?= $isLive ? 'تا امروز' : 'کل ماه' ?></small>
            <b><?= money((int) $M['revenue']) ?> <em>تومان</em></b>
            <div class="srp-big-sub"><?= $chip(sales_delta($M['revenue'], $P['revenue'])) ?> <span class="muted">نسبت به <?= e(jmonth_label($py, $pm)) ?> (<?= money((int) $P['revenue']) ?>)</span></div>
        </div>
        <?php if ($isLive): ?>
        <div class="srp-pace">
            <small>پیش‌بینی پایان ماه با همین سرعت</small>
            <b><?= money($pace) ?> <em>تومان</em></b>
            <span class="muted small">میانگین روزانه: <?= money((int) round($M['revenue'] / max(1, $elapsed))) ?> تومان</span>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="srp-kpis">
    <?php foreach ([
        ['✅', 'سفارش موفق', fa_num((string) $M['sold']), sales_delta($M['sold'], $P['sold']), fa_num((string) $P['sold']), false],
        ['🧾', 'میانگین هر سفارش', money((int) $M['avg']) . ' ت', sales_delta($M['avg'], $P['avg']), money((int) $P['avg']), false],
        ['👥', 'مشتری', fa_num((string) $M['customers']), sales_delta($M['customers'], $P['customers']), fa_num((string) $P['customers']), false],
        ['📦', 'اقلام فروخته‌شده', fa_num((string) $M['items']), sales_delta($M['items'], $P['items']), fa_num((string) $P['items']), false],
        ['🎯', 'نرخ موفقیت سفارش', fa_num((string) $M['conv']) . '٪', sales_delta($M['conv'], $P['conv']), fa_num((string) $P['conv']) . '٪', false],
        ['🏷️', 'تخفیف داده‌شده', money((int) $M['discount']) . ' ت', sales_delta($M['discount'], $P['discount']), money((int) $P['discount']), true],
    ] as [$ic, $lb, $v, $d, $pv, $inv]): ?>
        <div class="srp-kpi">
            <div class="srp-kpi-h"><span class="srp-kpi-ic"><?= $ic ?></span><small><?= e($lb) ?></small></div>
            <b><?= $v ?></b>
            <div class="srp-kpi-f"><?= $chip($d, $inv) ?><span class="muted">قبل: <?= $pv ?></span></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="srp-row">
    <section class="card srp-chart-card">
        <div class="card-head"><h3>📊 فروش روزانه‌ی <?= e(jmonth_label($sy, $sm)) ?></h3><small class="muted">روی هر ستون بروی تا مبلغ اون روز رو ببینی</small></div>
        <?php $dmax = max(1, max($M['daily'] ?: [0])); ?>
        <div class="srp-chart" role="img" aria-label="نمودار فروش روزانه">
            <div class="srp-grid" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <div class="srp-bars" style="--n:<?= $days ?>">
                <?php foreach ($M['daily'] as $i => $v): $day = $i + 1; $future = $isLive && $day > $elapsed; ?>
                    <div class="srp-bar<?= $future ? ' is-future' : '' ?><?= $isLive && $day === $elapsed ? ' is-today' : '' ?>" tabindex="0" data-tip="<?= e(jalali_month_names()[$sm] . ' ' . fa_num((string) $day) . ' · ' . money((int) $v) . ' تومان') ?>">
                        <i style="height:<?= $v ? max(3, round($v * 100 / $dmax, 1)) : 0 ?>%"></i>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="srp-axis" aria-hidden="true"><span>۱</span><span><?= fa_num((string) (int) ceil($days / 2)) ?></span><span><?= fa_num((string) $days) ?></span></div>
            <div class="srp-tip" hidden></div>
        </div>
        <div class="srp-chart-foot muted small">بیشترین فروش یک روز: <b><?= money($dmax > 1 ? $dmax : 0) ?></b> تومان</div>
    </section>

    <section class="card srp-top-card">
        <div class="card-head"><h3>🏆 پرفروش‌ترین‌ها</h3></div>
        <?php if (!$M['top']): ?>
            <div class="srp-empty">هنوز فروشی ثبت نشده</div>
        <?php else: $tmax = max(1, $M['top'][0][2]); foreach ($M['top'] as $i => [$tt, $tq, $tv]): ?>
            <div class="srp-top">
                <span class="srp-rank"><?= fa_num((string) ($i + 1)) ?></span>
                <div class="srp-top-b">
                    <div class="srp-top-l"><b><?= e($tt) ?></b><span><?= money($tv) ?> ت</span></div>
                    <div class="srp-meter"><i style="width:<?= round($tv * 100 / $tmax) ?>%"></i></div>
                    <small class="muted"><?= fa_num((string) $tq) ?> عدد</small>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </section>
</div>

<section class="card srp-sec">
    <div class="card-head"><h3>⚖️ مقایسه با ماه قبل</h3><small class="muted"><?= e(jmonth_label($sy, $sm)) ?> در برابر <?= e(jmonth_label($py, $pm)) ?></small></div>
    <div class="tbl-wrap">
        <table class="tbl srp-cmp">
            <thead><tr><th>شاخص</th><th><?= e(jmonth_label($sy, $sm)) ?></th><th><?= e(jmonth_label($py, $pm)) ?></th><th>تغییر</th></tr></thead>
            <tbody>
            <?php foreach ([
                ['💰 فروش (تومان)', 'revenue', 'm', false], ['✅ سفارش موفق', 'sold', 'n', false], ['🛒 کل سفارش‌های ثبت‌شده', 'orders', 'n', false],
                ['✖️ لغو، منقضی یا ردشده', 'lost', 'n', true], ['🧾 میانگین هر سفارش', 'avg', 'm', false], ['👥 مشتری', 'customers', 'n', false],
                ['📦 اقلام فروخته‌شده', 'items', 'n', false], ['🎯 نرخ موفقیت', 'conv', '%', false], ['🏷️ تخفیف داده‌شده', 'discount', 'm', true], ['🙋 عضو جدید سایت', 'members', 'n', false],
            ] as [$lb, $k, $f, $inv]):
                $fmt = fn($v) => $f === 'm' ? money((int) $v) : ($f === '%' ? fa_num((string) $v) . '٪' : fa_num(number_format((int) $v))); ?>
                <tr><td><?= e($lb) ?></td><td><b><?= $fmt($M[$k] ?? 0) ?></b></td><td class="muted"><?= $fmt($P[$k] ?? 0) ?></td><td><?= $chip(sales_delta($M[$k] ?? 0, $P[$k] ?? 0), $inv) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card srp-sec">
    <div class="card-head">
        <h3>🗓️ همه‌ی ماه‌ها</h3>
        <div class="row-flex">
            <small class="muted"><?= fa_num((string) count($all)) ?> ماه · هر ماه آخرش خودکار بسته می‌شه</small>
            <?php if (can('*')): ?><form method="post"><?= csrf_field() ?><button class="abtn abtn-ghost abtn-sm" name="act" value="rebuild" data-confirm="همه‌ی ماه‌های بسته‌شده دوباره از روی سفارش‌ها حساب شوند؟">🔄 بازسازی</button></form><?php endif; ?>
        </div>
    </div>
    <div class="srp-months">
        <?php foreach ($slice as [$y, $m]):
            $k = jmonth_key($y, $m); $d = sales_month($y, $m); [$qy, $qm] = jmonth_prev($y, $m); $q = sales_month($qy, $qm); $live = $k === $curKey; ?>
            <a class="srp-month<?= $k === $selKey ? ' is-on' : '' ?>" href="<?= e($link(['m' => $k])) ?>">
                <div class="srp-month-h">
                    <span class="srp-month-n"><b><?= e(jalali_month_names()[$m]) ?></b><small><?= fa_num((string) $y) ?></small></span>
                    <span class="srp-state<?= $live ? ' is-live' : '' ?>"><?= $live ? '<i></i> در جریان' : '🔒 بسته شد' ?></span>
                    <?= $chip(sales_delta($d['revenue'], $q['revenue'])) ?>
                </div>
                <div class="srp-month-v"><b><?= money((int) $d['revenue']) ?></b><em>تومان</em></div>
                <div class="srp-meter"><i style="width:<?= round($d['revenue'] * 100 / $maxRev) ?>%"></i></div>
                <div class="srp-month-f">
                    <span>✅ <?= fa_num((string) $d['sold']) ?> موفق</span>
                    <span>🛒 <?= fa_num((string) $d['orders']) ?> سفارش</span>
                    <span>🧾 میانگین <?= money((int) $d['avg']) ?></span>
                    <span>👥 <?= fa_num((string) $d['customers']) ?> مشتری</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination srp-pager" aria-label="صفحه‌بندی ماه‌ها">
            <?php if ($page > 1): ?><a class="pg-btn" href="<?= e($link(['p' => $page - 1])) ?>">‹ جدیدتر</a><?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?><a class="pg-num<?= $i === $page ? ' active' : '' ?>" href="<?= e($link(['p' => $i])) ?>"><?= fa_num((string) $i) ?></a><?php endfor; ?>
            <?php if ($page < $pages): ?><a class="pg-btn" href="<?= e($link(['p' => $page + 1])) ?>">قدیمی‌تر ›</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var ch = document.querySelector('.srp-chart'); if (!ch) return;
    var tip = ch.querySelector('.srp-tip');
    var show = function (b) {
        if (!b) { tip.hidden = true; return; }
        tip.textContent = b.dataset.tip; tip.hidden = false;
        var cr = ch.getBoundingClientRect(), br = b.getBoundingClientRect(), w = tip.offsetWidth;
        var x = br.left - cr.left + br.width / 2 - w / 2; x = Math.max(0, Math.min(cr.width - w, x));
        tip.style.left = x + 'px';
    };
    ch.addEventListener('mouseover', function (e) { show(e.target.closest('.srp-bar')); });
    ch.addEventListener('mouseleave', function () { show(null); });
    ch.addEventListener('focusin', function (e) { show(e.target.closest('.srp-bar')); });
    ch.addEventListener('click', function (e) { show(e.target.closest('.srp-bar')); });
});
</script>
<?php adm_foot();
