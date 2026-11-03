<?php
/**
 * صفحه سینمایی ورود / ثبت‌نام اعضا
 */
$colors = theme_colors();
$logo = media_url((string) setting('logo'));
$siteTitle = (string) setting('site_title');
$canRegister = setting_on('members_register');
$cfg = ['base' => nx_base_path(), 'csrf' => csrf_token(), 'home' => url(''), 'canRegister' => $canRegister];
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#000000">
<title>ورود | ثبت‌نام — <?= e($siteTitle) ?></title>
<link rel="icon" href="<?= e(media_url((string) setting('favicon'))) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/Vazirmatn-Variable.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e($logo) ?>" as="image">
<link rel="stylesheet" href="<?= e(asset('css/warp.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/auth.css')) ?>">
<style><?= theme_css_vars() ?></style>
<script>try{if(sessionStorage.getItem('nx_warp'))document.documentElement.classList.add('warp-in')}catch(e){}</script>
</head>
<body class="au-body">
<div class="nx-warp" id="nxWarp" aria-hidden="true"><div class="nx-warp-disc"></div><div class="nx-warp-core"><span class="nx-warp-ring"></span><img src="<?= e($logo) ?>" alt=""></div></div>

<!-- ============ Scene ============ -->
<div class="au-scene" aria-hidden="true">
    <div class="au-sky"></div>
    <div class="au-sunglow"><i></i></div>
    <div class="au-sun"></div>
    <div class="au-floor"><div class="au-grid"></div></div>
    <div class="au-horizon"></div>
    <div class="au-beams"><i></i><i></i><i></i></div>
    <div class="au-fog"><i></i><i></i></div>
    <canvas id="auFx"></canvas>
    <div class="au-scan"></div>
    <div class="au-grain"></div>
    <div class="au-vignette"></div>
    <div class="au-flash"></div>
</div>

<div class="au-bar au-bar-top">
    <span class="au-rec"><i></i> REC</span>
    <span class="au-hud-mid">NEXUS // ACCESS GATE</span>
    <span class="au-tc" id="auTc">00:00:00:00</span>
</div>
<div class="au-bar au-bar-bottom">
    <span>SCENE 01 — <?= jdate('Y/m/d', null, false) ?></span>
    <span class="au-hud-mid" id="auHudState">STANDBY</span>
    <span>4K · 24FPS</span>
</div>
<div class="au-corners" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

<a class="au-back" href="<?= e(url('')) ?>" data-warp><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg> بازگشت به سایت</a>

<!-- ============ Intro ============ -->
<main class="au-intro" id="auIntro">
    <div class="au-emblem">
        <svg class="au-rings" viewBox="0 0 200 200" aria-hidden="true">
            <circle class="r1" cx="100" cy="100" r="96"/>
            <circle class="r2" cx="100" cy="100" r="86"/>
            <circle class="r3" cx="100" cy="100" r="76"/>
            <g class="ticks"><?php for ($i = 0; $i < 60; $i++): $a = $i * 6 * M_PI / 180; $r1 = $i % 5 ? 90 : 87; ?><line x1="<?= round(100 + $r1 * cos($a), 2) ?>" y1="<?= round(100 + $r1 * sin($a), 2) ?>" x2="<?= round(100 + 93 * cos($a), 2) ?>" y2="<?= round(100 + 93 * sin($a), 2) ?>"/><?php endfor; ?></g>
        </svg>
        <span class="au-shock"></span><span class="au-shock d2"></span>
        <img src="<?= e($logo) ?>" alt="<?= e($siteTitle) ?>" id="auLogo" decoding="async">
    </div>
    <h1 class="au-title" id="auTitle" data-text="<?= e($siteTitle) ?>"><?= e($siteTitle) ?></h1>
    <p class="au-tagline"><span id="auType"></span><i class="au-caret"></i></p>
    <button class="au-start" id="auStart" type="button">
        <span class="au-start-glow"></span>
        <span class="au-start-label">ورود و ثبت‌نام</span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    </button>
    <p class="au-hint">فقط با شماره موبایل، ایمیل یا نام کاربری</p>
</main>

<!-- ============ Auth card ============ -->
<section class="au-stage" id="auStage" hidden>
    <div class="au-card" id="auCard">
        <span class="au-card-light" aria-hidden="true"></span>
        <header class="au-card-head">
            <button class="au-icon-btn" id="auBackStep" type="button" aria-label="مرحله قبل" hidden><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
            <span class="au-card-brand"><img src="<?= e($logo) ?>" alt="">NEXUS <b>ACCESS</b></span>
            <span class="au-steps-dots" id="auDots"><i class="on"></i><i></i><i></i></span>
        </header>

        <div class="au-steps" id="auSteps">
            <!-- 1: identify -->
            <form class="au-step" data-step="identify" novalidate>
                <h2>خوش اومدی 👋</h2>
                <p class="au-sub">شماره موبایل، ایمیل یا نام کاربری‌ات رو وارد کن. اگه حساب داشته باشی وارد می‌شی، نداشته باشی حسابت رو می‌سازیم.</p>
                <label class="au-field au-id-field">
                    <span class="au-id-icon" id="auIdIcon"></span>
                    <input type="text" name="identifier" id="auIdentifier" dir="ltr" autocomplete="username" placeholder="0912… / name@mail.com / username" required>
                    <em class="au-id-type" id="auIdType"></em>
                </label>
                <div class="au-error" role="alert"></div>
                <button class="au-btn" type="submit"><span>ادامه</span><i class="au-spin"></i></button>
            </form>

            <!-- 2: login -->
            <form class="au-step" data-step="login" novalidate hidden>
                <div class="au-hello">
                    <span class="au-avatar" id="auAvatar">N</span>
                    <div><h2>سلام <span id="auHelloName"></span>! 👋</h2><p class="au-sub">خوشحالیم که برگشتی. رمز عبورت رو وارد کن.</p></div>
                </div>
                <div class="au-chip"><span id="auLoginId" dir="ltr"></span><button type="button" data-goto="identify">تغییر</button></div>
                <label class="au-field">
                    <input type="password" name="password" id="auPassword" dir="ltr" autocomplete="current-password" placeholder=" " required>
                    <span class="au-flabel">رمز عبور</span>
                </label>
                <div class="au-error" role="alert"></div>
                <button class="au-btn" type="submit"><span>ورود</span><i class="au-spin"></i></button>
            </form>

            <!-- 3: register -->
            <form class="au-step au-step-wide" data-step="register" novalidate hidden>
                <h2>ساخت حساب جدید ✨</h2>
                <p class="au-sub"><b dir="ltr" id="auRegId"></b> هنوز عضو نیست. چند ثانیه‌ای حسابت رو بساز.</p>
                <input type="text" name="website" class="au-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="au-grid2">
                    <label class="au-field"><input name="first_name" maxlength="60" autocomplete="given-name" placeholder=" " required><span class="au-flabel">نام</span><em class="au-ferr"></em></label>
                    <label class="au-field"><input name="last_name" maxlength="60" autocomplete="family-name" placeholder=" " required><span class="au-flabel">نام خانوادگی</span><em class="au-ferr"></em></label>
                    <label class="au-field"><input name="phone" dir="ltr" inputmode="tel" autocomplete="tel" placeholder=" " required><span class="au-flabel">شماره موبایل</span><em class="au-ferr"></em></label>
                    <label class="au-field"><input name="email" type="email" dir="ltr" autocomplete="email" placeholder=" " required><span class="au-flabel">ایمیل</span><em class="au-ferr"></em></label>
                    <label class="au-field"><input name="username" dir="ltr" maxlength="30" autocomplete="username" placeholder=" " required><span class="au-flabel">نام کاربری (انگلیسی)</span><em class="au-ferr"></em></label>
                    <label class="au-field">
                        <input type="password" name="password" dir="ltr" autocomplete="new-password" placeholder=" " required>
                        <span class="au-flabel">رمز عبور</span>
                        <em class="au-ferr"></em>
                    </label>
                </div>
                <div class="au-strength" id="auStrength"><i></i><i></i><i></i><i></i><span>حداقل ۸ کاراکتر، شامل حروف انگلیسی و عدد</span></div>
                <div class="au-error" role="alert"></div>
                <button class="au-btn" type="submit"><span>ثبت‌نام</span><i class="au-spin"></i></button>
            </form>

            <!-- 4: success -->
            <div class="au-step au-success" data-step="success" hidden>
                <div class="au-check">
                    <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="c" cx="60" cy="60" r="52"/><path class="k" d="M36 62 l16 16 l32 -34"/></svg>
                    <span class="au-check-burst"></span><span class="au-check-burst d2"></span>
                </div>
                <h2>ثبت‌نام شدید! 🎉</h2>
                <p class="au-sub"><b id="auSucName"></b> عزیز، به خانواده‌ی NEXUS خوش اومدی.</p>
                <div class="au-mini-card">
                    <span class="au-mini-holo"></span>
                    <div class="au-mini-top"><span><img src="<?= e($logo) ?>" alt="">NEXUS</span><em>MEMBER</em></div>
                    <div class="au-mini-code" id="auSucCode">NX-000000</div>
                    <div class="au-mini-bottom"><span><small>CARD HOLDER</small><b id="auSucFull"></b></span><span><small>MEMBER SINCE</small><b id="auSucDate"></b></span></div>
                </div>
                <p class="au-sub small">این <b>کد عضویت اختصاصی</b> توئه؛ توی حساب کاربری همیشه در دسترسه.</p>
                <button class="au-btn au-btn-xl" type="button" id="auFinish"><span>ورود به حساب</span><i class="au-spin"></i></button>
            </div>
        </div>
    </div>
</section>

<!-- ============ Access granted sequence ============ -->
<div class="au-enter" id="auEnter" hidden>
    <div class="au-enter-core">
        <svg class="au-enter-rings" viewBox="0 0 200 200" aria-hidden="true"><circle class="e1" cx="100" cy="100" r="92"/><circle class="e2" cx="100" cy="100" r="78"/><circle class="e3" cx="100" cy="100" r="64"/></svg>
        <b class="au-enter-pct" id="auPct">۰٪</b>
    </div>
    <div class="au-term" id="auTerm" dir="ltr"></div>
    <div class="au-granted" id="auGranted">
        <strong data-text="ACCESS GRANTED">ACCESS GRANTED</strong>
        <span>خوش اومدی <b id="auWelcomeName"></b> 🔥</span>
    </div>
</div>

<script>window.NXAU = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(asset('js/auth.js')) ?>" defer></script>
<script src="<?= e(asset('js/pw.js')) ?>" defer></script>
</body>
</html>
