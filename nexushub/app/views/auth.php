<?php
/**
 * صفحه سینمایی ورود / ثبت‌نام اعضا
 */
$colors = theme_colors();
$logo = logo_url();
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
<?php $decorHtml = decor_html('auth'); if ($decorHtml): ?><link rel="stylesheet" href="<?= e(asset('css/decor.css')) ?>"><?php endif; ?>
<style><?= theme_css_vars() ?></style>
<script>try{var w=+sessionStorage.getItem('nx_warp');if(w){var d=document.documentElement,el=Date.now()-w;d.classList.add('warp-in');if(w>1&&el>=0&&el<20000)d.style.setProperty('--wt',-el+'ms')}}catch(e){}</script>
</head>
<body class="au-body">
<?= $decorHtml ?>
<div class="nx-warp" id="nxWarp" aria-hidden="true"><div class="nx-warp-disc"></div><div class="nx-warp-fx"><i class="wf-aura"></i><i class="wf-grid"></i><i class="wf-sonar"></i><i class="wf-sonar s2"></i><i class="wf-sonar s3"></i><i class="wf-sweep"></i><i class="wf-scan"></i><i class="wf-corner c1"></i><i class="wf-corner c2"></i><i class="wf-corner c3"></i><i class="wf-corner c4"></i><span class="wf-code">NEXUS://ACCESS · SECURE LINK</span></div><div class="nx-warp-core"><span class="wc-orb"><span class="wc-glow"></span><span class="wc-ring r3"></span><span class="wc-ring r1"><i class="wc-dot"></i></span><span class="wc-ring r2"></span><img src="<?= e($logo) ?>" alt=""></span><span class="wc-name">NEXUS<b>|</b>HUB</span><span class="wc-bar"><i></i></span></div></div>

<!-- ============ Scene ============ -->
<div class="au-scene" aria-hidden="true">
    <div class="au-space"></div>
    <div class="au-nebula"><i></i><i></i><i></i></div>
    <canvas id="auFx"></canvas>
    <div class="au-planet-wrap"><div class="au-planet"></div></div>
    <div class="au-bloom"></div>
    <div class="au-vignette"></div>
    <div class="au-flash"></div>
</div>


<a class="au-back" href="<?= e(url('')) ?>" data-warp><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg> بازگشت به سایت</a>

<!-- ============ Intro ============ -->
<main class="au-intro" id="auIntro">
    <?php $bundled = in_array((string) setting('logo'), ['', 'assets/img/logo-256.png', 'assets/img/logo.jpg'], true);
          $logoStyle = $bundled ? (string) setting('auth_logo_style', 'particles') : 'particles';
          if (!in_array($logoStyle, ['particles', 'laser', 'glass'], true)) $logoStyle = 'particles'; ?>
    <div class="au-emblem st-<?= e($logoStyle) ?>" id="auEmblem" data-style="<?= e($logoStyle) ?>"<?= $bundled ? ' data-mark="' . e(url('assets/img/logo-mark.png')) . '"' : '' ?>>
        <img src="<?= e($logo) ?>" alt="<?= e($siteTitle) ?>" id="auLogo" decoding="async">
        <?php if ($logoStyle === 'laser'): ?>
        <svg class="lx" viewBox="0 0 512 512" aria-hidden="true">
            <defs><linearGradient id="lxFill" x1="0" y1="0" x2="1" y2="1"><stop offset="0" style="stop-color:var(--primary);stop-opacity:.9"/><stop offset=".55" style="stop-color:var(--primary);stop-opacity:.42"/><stop offset="1" style="stop-color:var(--primary);stop-opacity:.85"/></linearGradient>
                <linearGradient id="lxShine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#ffd0d6" stop-opacity=".55"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
                <clipPath id="lxClip"><path d="<?= $markD = "M493.2 73.8 L494.3 73.7 L495.2 73.8 L496.0 74.0 L496.6 74.4 L496.9 75.1 L497.2 75.7 L497.2 76.4 L497.1 77.1 L496.9 77.9 L495.1 80.5 L491.7 85.0 L486.8 91.4 L480.2 99.6 L475.2 106.2 L471.6 111.1 L469.4 114.2 L468.6 115.8 L466.8 118.6 L463.9 122.7 L460.0 128.1 L455.0 134.9 L451.1 140.0 L448.3 143.4 L446.6 145.2 L445.9 145.3 L444.8 144.3 L443.2 142.2 L441.1 138.9 L438.4 134.6 L436.5 131.0 L435.2 128.3 L434.5 126.5 L434.5 125.5 L434.6 124.5 L434.9 123.5 L435.2 122.5 L435.8 121.5 L436.7 120.4 L438.0 119.2 L439.7 117.9 L441.8 116.6 L444.3 114.8 L447.1 112.6 L450.2 110.0 L453.8 107.0 L457.1 104.0 L460.2 101.0 L463.1 98.0 L465.9 95.0 L468.4 92.5 L470.6 90.5 L472.6 89.0 L474.4 88.0 L476.6 86.5 L479.3 84.4 L482.4 81.8 L486.1 78.7 L489.1 76.3 L491.4 74.7ZM16.2 78.8 L17.3 78.7 L18.7 79.2 L20.2 80.2 L22.0 81.9 L24.0 84.1 L25.9 86.0 L27.8 87.5 L29.6 88.6 L31.4 89.4 L33.1 90.5 L34.9 92.0 L36.6 93.9 L38.4 96.1 L39.9 97.9 L41.3 99.1 L42.5 99.9 L43.5 100.1 L44.9 100.8 L46.6 101.9 L48.8 103.5 L51.2 105.5 L53.7 107.7 L56.0 110.0 L58.2 112.4 L60.3 115.1 L62.3 117.3 L64.3 119.3 L66.1 120.9 L67.9 122.1 L69.2 123.3 L70.1 124.4 L70.5 125.5 L70.5 126.5 L69.7 128.9 L68.0 132.7 L65.4 137.9 L62.1 144.6 L59.4 149.5 L57.5 152.8 L56.2 154.5 L55.8 154.5 L55.2 154.2 L54.5 153.7 L53.7 152.8 L52.8 151.7 L51.7 149.8 L50.2 147.3 L48.5 144.0 L46.5 140.0 L44.8 136.8 L43.4 134.4 L42.4 132.9 L41.6 132.1 L40.7 130.7 L39.6 128.6 L38.2 125.8 L36.8 122.2 L35.4 119.4 L34.1 117.1 L33.0 115.5 L32.0 114.5 L31.1 113.4 L30.4 112.1 L29.8 110.8 L29.2 109.2 L28.1 107.0 L26.4 104.0 L24.0 100.2 L21.0 95.8 L18.6 92.0 L16.7 89.0 L15.4 86.8 L14.6 85.2 L14.1 83.9 L13.9 82.6 L13.9 81.5 L14.1 80.5 L14.6 79.7 L15.3 79.2ZM215.4 113.8 L216.1 113.7 L216.6 113.7 L217.1 113.8 L217.6 114.1 L217.9 114.4 L220.7 118.7 L225.7 126.7 L233.1 138.6 L242.9 154.4 L250.2 173.2 L255.1 195.2 L257.5 220.4 L257.5 248.6 L257.3 270.0 L257.0 284.4 L256.6 291.9 L255.9 292.6 L255.2 292.9 L254.5 293.1 L253.7 292.9 L252.8 292.6 L252.1 286.2 L251.5 273.9 L251.1 255.6 L250.9 231.4 L250.4 212.9 L249.8 200.3 L249.0 193.5 L248.0 192.5 L247.2 191.4 L246.5 190.2 L245.9 188.9 L245.6 187.6 L245.1 186.5 L244.5 185.8 L243.9 185.5 L243.1 185.5 L242.5 185.6 L242.0 185.9 L241.6 186.2 L241.4 186.8 L240.9 187.1 L240.1 187.4 L239.1 187.5 L237.9 187.5 L236.7 187.7 L235.7 188.2 L234.7 188.8 L233.8 189.7 L233.0 190.8 L232.3 192.1 L231.8 193.6 L231.2 195.4 L230.6 197.1 L229.7 198.9 L228.6 200.6 L227.4 202.4 L226.4 203.9 L225.8 205.3 L225.5 206.5 L225.5 207.5 L225.2 209.3 L224.6 211.9 L223.6 215.4 L222.4 219.6 L221.4 226.1 L220.7 234.8 L220.3 245.7 L220.2 258.8 L219.8 268.5 L219.1 274.8 L218.1 277.8 L216.9 277.2 L212.8 273.8 L205.9 267.2 L196.2 257.8 L183.8 245.2 L173.6 235.5 L165.7 228.5 L160.1 224.2 L156.9 222.8 L153.5 221.0 L150.0 219.0 L146.4 216.8 L142.6 214.2 L139.3 212.2 L136.4 210.6 L134.0 209.4 L132.0 208.6 L130.2 207.8 L128.8 206.8 L127.5 205.6 L126.5 204.4 L125.6 203.5 L124.7 203.0 L123.9 202.9 L123.1 203.1 L122.2 203.0 L121.1 202.5 L119.8 201.6 L118.2 200.4 L116.8 199.5 L115.2 199.0 L113.8 198.9 L112.2 199.1 L110.8 199.0 L109.4 198.5 L108.1 197.6 L106.9 196.4 L105.2 195.1 L103.2 193.9 L100.9 192.6 L98.1 191.4 L96.0 191.1 L94.6 191.8 L93.8 193.4 L93.7 196.1 L93.7 198.5 L93.9 200.7 L94.2 202.6 L94.8 204.4 L95.1 205.9 L95.2 207.1 L95.1 208.1 L94.9 208.9 L94.9 209.8 L95.1 210.8 L95.6 211.9 L96.4 213.1 L97.0 221.4 L97.5 236.6 L97.9 258.9 L98.1 288.1 L98.6 310.3 L99.3 325.3 L100.2 333.3 L101.3 334.2 L102.3 334.8 L103.3 335.3 L104.1 335.5 L104.9 335.5 L105.6 335.2 L106.2 334.8 L106.8 334.0 L107.2 333.0 L107.9 332.2 L108.6 331.8 L109.5 331.5 L110.5 331.5 L112.2 330.5 L114.7 328.4 L117.8 325.3 L121.7 321.2 L124.8 317.6 L127.2 314.4 L128.8 311.8 L129.7 309.7 L130.4 308.2 L131.1 307.3 L131.6 307.1 L131.9 307.4 L132.2 308.3 L132.4 309.8 L132.5 311.8 L132.5 314.2 L132.6 316.2 L132.7 317.6 L132.9 318.4 L133.1 318.6 L133.3 319.1 L133.4 319.7 L133.5 320.5 L133.5 321.5 L132.1 323.6 L129.2 326.9 L124.9 331.2 L119.1 336.8 L114.7 341.1 L111.6 344.4 L109.8 346.5 L109.2 347.5 L108.5 348.6 L107.6 349.8 L106.4 351.1 L105.1 352.4 L103.8 353.5 L102.8 354.2 L101.9 354.5 L101.1 354.5 L99.9 353.5 L98.2 351.6 L96.1 348.7 L93.4 344.8 L91.5 341.8 L90.3 339.8 L89.9 338.6 L90.1 338.4 L90.4 330.7 L90.6 315.6 L90.9 293.0 L91.1 263.0 L89.9 238.1 L87.3 218.2 L83.2 203.4 L77.8 193.6 L74.2 184.6 L72.8 176.4 L73.2 168.9 L75.8 162.1 L78.1 156.4 L80.3 151.7 L82.3 148.1 L84.2 145.4 L85.8 143.5 L87.3 142.3 L88.5 141.9 L89.5 142.1 L90.8 142.9 L92.2 144.3 L94.0 146.2 L96.0 148.8 L97.7 150.6 L99.1 151.9 L100.1 152.5 L100.9 152.5 L101.5 152.6 L102.1 152.8 L102.6 153.1 L102.9 153.4 L103.3 154.0 L103.6 154.7 L103.9 155.5 L104.1 156.5 L105.0 157.9 L106.4 159.7 L108.4 161.9 L111.1 164.6 L113.6 167.0 L116.0 169.2 L118.4 171.1 L120.6 172.9 L127.8 179.6 L139.8 191.4 L156.6 208.1 L178.4 229.9 L194.8 246.2 L205.9 257.1 L211.8 262.5 L212.2 262.5 L212.7 262.4 L213.2 262.2 L213.6 261.9 L213.9 261.6 L214.2 257.9 L214.4 251.0 L214.5 240.8 L214.5 227.2 L214.4 217.1 L214.3 210.2 L214.1 206.6 L213.9 206.4 L213.7 205.2 L213.6 203.1 L213.5 200.0 L213.5 196.0 L213.4 192.8 L213.1 190.2 L212.8 188.5 L212.2 187.5 L211.9 186.1 L211.6 184.2 L211.5 181.9 L211.5 179.1 L211.4 177.0 L211.3 175.5 L211.1 174.6 L210.9 174.4 L210.6 171.8 L210.4 166.9 L210.1 159.8 L209.9 150.2 L209.8 142.4 L209.9 136.3 L210.2 131.9 L210.8 129.1 L211.1 126.8 L211.2 124.9 L211.1 123.5 L210.9 122.5 L210.9 121.3 L211.3 119.9 L212.0 118.4 L213.0 116.6 L213.9 115.3 L214.7 114.3ZM265.9 134.8 L277.6 134.7 L286.7 134.7 L293.2 134.9 L297.2 135.2 L298.8 135.8 L301.2 137.6 L304.7 140.8 L309.1 145.3 L314.4 151.2 L320.4 158.0 L327.0 165.7 L334.1 174.2 L341.9 183.8 L348.0 191.2 L352.6 196.5 L355.6 199.7 L356.9 200.8 L358.6 201.0 L360.6 200.2 L362.9 198.6 L365.6 195.9 L367.7 193.7 L369.2 191.9 L370.2 190.5 L370.8 189.5 L372.0 187.8 L374.0 185.2 L376.8 182.0 L380.2 178.0 L383.1 174.2 L385.4 170.8 L387.0 167.5 L388.0 164.5 L389.5 161.5 L391.4 158.4 L393.8 155.3 L396.7 152.2 L399.6 149.3 L402.5 146.6 L405.5 144.1 L408.5 141.9 L411.0 140.2 L413.0 139.1 L414.5 138.5 L415.5 138.5 L416.5 138.7 L417.4 139.2 L418.3 139.8 L419.2 140.7 L420.5 142.8 L422.3 146.1 L424.6 150.6 L427.4 156.4 L429.3 161.1 L430.4 164.9 L430.8 167.6 L430.2 169.4 L429.5 171.1 L428.5 172.7 L427.2 174.2 L425.8 175.8 L422.4 180.0 L417.3 187.0 L410.4 196.8 L401.6 209.2 L394.8 218.8 L389.8 225.5 L386.8 229.3 L385.7 230.2 L384.8 230.6 L384.0 230.6 L383.4 230.2 L383.1 229.3 L382.9 228.3 L383.0 227.3 L383.2 226.1 L383.8 224.9 L384.0 223.9 L384.1 223.2 L383.9 222.8 L383.6 222.7 L383.2 222.8 L382.7 223.1 L382.2 223.6 L381.8 224.4 L381.0 225.2 L379.9 226.2 L378.6 227.2 L376.9 228.3 L375.6 229.4 L374.4 230.6 L373.6 231.7 L372.9 232.8 L372.3 233.7 L371.6 234.4 L370.9 234.9 L370.1 235.1 L369.3 235.6 L368.3 236.3 L367.3 237.2 L366.2 238.3 L365.0 239.7 L363.7 241.2 L362.2 243.0 L360.8 245.0 L359.6 246.7 L358.9 248.1 L358.5 249.1 L358.5 249.9 L358.8 250.7 L359.2 251.6 L360.0 252.5 L361.0 253.5 L361.8 254.6 L362.4 255.7 L362.9 256.9 L363.1 258.1 L363.6 259.4 L364.4 260.8 L365.4 262.2 L366.6 263.8 L367.6 265.1 L368.2 266.4 L368.5 267.5 L368.5 268.5 L369.6 270.4 L371.9 273.1 L375.2 276.8 L379.8 281.2 L383.2 284.9 L385.6 287.6 L386.9 289.5 L387.1 290.5 L387.6 291.5 L388.4 292.5 L389.4 293.5 L390.6 294.5 L391.7 295.6 L392.6 296.9 L393.2 298.2 L393.8 299.8 L394.9 301.6 L396.6 303.9 L399.0 306.5 L402.0 309.5 L404.5 312.2 L406.5 314.8 L408.0 317.0 L409.0 319.0 L410.1 320.9 L411.4 322.6 L412.8 324.2 L414.2 325.8 L415.5 327.1 L416.5 328.4 L417.2 329.5 L417.8 330.5 L418.1 331.5 L418.4 332.5 L418.5 333.5 L418.5 334.5 L418.0 336.0 L416.9 338.1 L415.3 340.7 L413.2 343.8 L411.3 346.0 L409.7 347.2 L408.3 347.6 L407.2 346.9 L406.0 345.8 L404.8 344.3 L403.6 342.2 L402.4 339.8 L400.2 336.1 L397.1 331.4 L393.0 325.5 L388.0 318.5 L384.4 313.0 L382.1 309.0 L381.2 306.5 L381.8 305.5 L382.1 304.5 L382.4 303.5 L382.5 302.5 L382.5 301.5 L382.3 300.6 L381.9 299.9 L381.4 299.2 L380.6 298.8 L379.9 297.8 L379.1 296.2 L378.4 294.2 L377.6 291.8 L376.9 289.6 L376.1 287.7 L375.4 286.1 L374.6 284.9 L373.8 283.8 L372.9 282.8 L372.0 281.9 L371.0 281.1 L370.1 280.1 L369.2 278.9 L368.4 277.4 L367.6 275.6 L366.8 274.1 L365.8 272.7 L364.6 271.5 L363.4 270.5 L362.1 269.2 L360.7 267.8 L359.2 266.0 L357.8 264.0 L356.6 262.2 L355.7 260.8 L355.1 259.5 L354.9 258.5 L354.5 257.7 L354.1 257.2 L353.6 256.8 L352.9 256.7 L352.3 256.9 L351.7 257.4 L351.1 258.2 L350.4 259.3 L349.0 261.2 L346.8 263.9 L343.9 267.4 L340.1 271.6 L337.2 275.2 L335.2 278.1 L334.1 280.2 L333.9 281.8 L333.5 283.1 L333.1 284.3 L332.6 285.3 L331.9 286.2 L331.2 286.8 L330.2 287.3 L329.1 287.5 L327.9 287.5 L326.8 287.7 L326.0 288.2 L325.4 288.8 L325.1 289.7 L324.3 290.8 L323.1 292.1 L321.5 293.6 L319.5 295.4 L317.9 296.9 L316.6 298.3 L315.8 299.5 L315.2 300.5 L314.3 301.8 L313.0 303.3 L311.3 305.2 L309.2 307.3 L307.3 309.0 L305.6 310.2 L304.1 310.9 L302.9 311.1 L301.6 311.8 L300.2 312.8 L298.8 314.1 L297.2 315.9 L295.7 317.4 L294.1 318.8 L292.4 320.0 L290.6 321.0 L288.8 322.4 L286.9 324.1 L285.0 326.2 L283.0 328.8 L281.0 330.9 L279.0 332.6 L277.0 334.0 L275.0 335.0 L273.4 335.9 L272.1 336.8 L271.2 337.6 L270.8 338.4 L270.2 338.9 L269.8 339.3 L269.2 339.5 L268.8 339.5 L268.0 339.8 L267.1 340.5 L265.9 341.6 L264.6 342.9 L263.2 344.5 L261.7 346.3 L260.2 348.4 L258.8 350.6 L256.4 353.8 L253.1 357.8 L249.0 362.6 L244.0 368.4 L240.2 372.9 L237.6 376.1 L236.1 378.1 L235.9 378.9 L235.8 379.5 L235.8 380.1 L236.1 380.6 L236.4 380.9 L237.3 381.0 L238.6 380.7 L240.4 380.0 L242.6 379.0 L244.4 378.3 L245.7 377.8 L246.6 377.7 L246.9 377.8 L247.2 378.0 L247.4 378.3 L247.5 378.8 L247.5 379.2 L245.9 381.0 L242.7 384.1 L237.9 388.4 L231.6 394.1 L225.8 399.0 L220.6 403.3 L216.0 407.0 L212.0 410.0 L208.9 412.0 L206.7 413.1 L205.4 413.2 L205.1 412.3 L205.0 411.3 L205.2 410.3 L205.6 409.1 L206.4 407.9 L207.2 406.6 L208.2 405.4 L209.4 404.1 L210.6 402.9 L213.4 399.3 L217.8 393.4 L223.8 385.2 L231.2 374.8 L238.1 365.4 L244.4 357.1 L250.0 350.0 L255.0 344.0 L258.8 339.3 L261.4 335.9 L262.9 333.9 L263.1 333.1 L266.8 328.1 L273.9 318.7 L284.5 305.0 L298.5 287.0 L309.5 272.9 L317.6 262.7 L322.7 256.4 L324.8 254.1 L326.7 252.3 L328.2 251.1 L329.5 250.5 L330.5 250.5 L331.3 250.6 L332.0 250.8 L332.6 251.1 L332.9 251.4 L333.2 252.0 L333.4 252.8 L333.5 253.9 L333.5 255.1 L333.7 256.1 L334.0 256.8 L334.4 257.2 L335.1 257.3 L335.9 256.9 L336.9 256.1 L338.1 254.7 L339.4 252.8 L340.3 251.0 L340.6 249.3 L340.4 247.8 L339.6 246.2 L338.5 244.6 L337.0 242.9 L335.1 241.0 L332.9 239.0 L330.4 236.6 L327.8 233.9 L325.0 230.8 L322.0 227.2 L317.2 221.5 L310.8 213.5 L302.5 203.2 L292.5 190.8 L283.5 179.7 L275.5 170.1 L268.5 161.9 L262.5 155.1 L257.6 149.8 L253.9 145.8 L251.2 143.1 L249.8 141.9 L248.7 140.6 L248.1 139.4 L247.9 138.1 L248.1 136.9 L251.2 135.9 L257.2 135.2ZM321.6 177.8 L322.9 177.7 L324.1 177.8 L325.0 178.1 L325.8 178.6 L326.2 179.4 L326.9 180.0 L327.6 180.5 L328.5 180.9 L329.5 181.1 L330.4 181.7 L331.2 182.5 L331.9 183.6 L332.6 184.9 L333.3 186.2 L334.3 187.2 L335.4 188.1 L336.6 188.9 L338.9 191.0 L342.1 194.5 L346.4 199.4 L351.6 205.6 L355.6 210.6 L358.2 214.2 L359.5 216.5 L359.5 217.5 L359.2 218.7 L358.7 220.0 L357.8 221.4 L356.7 223.1 L355.5 224.3 L354.3 225.3 L353.1 225.9 L351.9 226.1 L350.7 226.2 L349.6 226.1 L348.5 225.8 L347.5 225.2 L346.3 224.3 L344.8 223.0 L343.2 221.3 L341.3 219.2 L339.8 217.4 L338.8 216.0 L338.1 214.9 L337.9 214.1 L337.3 213.3 L336.4 212.4 L335.2 211.5 L333.8 210.5 L332.6 209.5 L331.7 208.5 L331.1 207.5 L330.9 206.5 L330.4 205.4 L329.8 204.3 L329.0 203.1 L328.0 201.9 L327.2 200.8 L326.8 199.8 L326.5 198.9 L326.5 198.1 L326.0 197.1 L325.0 195.7 L323.5 194.0 L321.5 192.0 L320.0 189.9 L319.0 187.6 L318.5 185.2 L318.5 182.8 L318.6 180.8 L318.7 179.4 L318.9 178.6 L319.1 178.4 L319.7 178.2 L320.5 178.0ZM287.2 229.0 L287.8 229.0 L288.4 229.8 L289.3 231.2 L290.4 233.5 L291.6 236.5 L293.3 239.3 L295.4 241.9 L298.0 244.4 L301.0 246.6 L302.4 249.1 L302.3 251.7 L300.6 254.5 L297.4 257.5 L294.7 260.5 L292.5 263.4 L290.8 266.3 L289.7 269.2 L288.6 271.2 L287.6 272.3 L286.7 272.6 L285.8 271.9 L285.0 270.8 L284.2 269.3 L283.4 267.2 L282.6 264.8 L281.3 262.3 L279.4 259.9 L277.0 257.6 L274.0 255.4 L271.8 253.6 L270.2 252.2 L269.5 251.2 L269.5 250.8 L269.7 250.2 L270.0 249.7 L270.4 249.1 L271.1 248.4 L271.8 247.8 L272.8 247.3 L273.9 246.8 L275.1 246.2 L276.4 245.5 L277.7 244.6 L279.1 243.4 L280.4 242.1 L281.7 240.3 L282.9 238.3 L284.0 235.9 L285.0 233.1 L285.9 231.1 L286.6 229.7ZM147.4 248.8 L148.1 248.7 L153.7 254.1 L164.3 264.9 L179.9 281.3 L200.6 303.2 L216.7 320.0 L228.2 331.8 L235.2 338.6 L237.8 340.4 L239.7 342.1 L241.0 343.8 L241.7 345.4 L241.8 347.1 L241.3 348.3 L240.3 349.3 L238.6 349.9 L236.4 350.1 L232.8 348.3 L227.8 344.5 L221.6 338.7 L213.9 330.8 L207.8 324.3 L203.1 319.3 L199.9 315.6 L198.1 313.4 L196.2 311.2 L194.2 309.1 L192.1 307.0 L189.9 305.0 L188.1 303.2 L186.7 301.8 L185.8 300.5 L185.2 299.5 L183.7 297.6 L181.1 294.9 L177.4 291.2 L172.6 286.8 L168.0 282.2 L163.5 277.6 L159.1 272.9 L154.9 268.1 L151.5 264.2 L149.0 261.2 L147.4 259.1 L146.6 257.9 L146.1 256.5 L145.9 255.0 L145.9 253.4 L146.1 251.6 L146.5 250.3 L146.9 249.3ZM447.2 329.2 L448.3 329.3 L449.2 329.5 L450.0 329.8 L450.6 330.1 L450.9 330.4 L451.3 331.0 L451.6 331.8 L451.9 332.9 L452.1 334.1 L452.9 335.8 L454.1 337.8 L455.9 340.1 L458.1 342.9 L459.9 345.3 L461.3 347.4 L462.2 349.2 L462.8 350.8 L465.4 355.0 L470.1 362.0 L477.0 371.8 L486.0 384.2 L492.8 394.0 L497.3 401.1 L499.7 405.4 L499.8 407.1 L499.6 408.3 L499.0 409.3 L498.1 409.9 L496.9 410.1 L495.8 410.2 L494.8 410.2 L494.1 409.9 L493.4 409.6 L493.0 409.2 L492.7 408.7 L492.5 408.2 L492.5 407.8 L492.3 407.2 L492.0 406.7 L491.6 406.1 L490.9 405.4 L490.0 404.9 L488.7 404.5 L487.0 404.1 L485.0 403.9 L483.2 403.4 L481.8 402.8 L480.5 402.0 L479.5 401.0 L476.0 398.4 L470.0 394.3 L461.5 388.6 L450.5 381.4 L441.7 375.5 L435.2 370.9 L430.8 367.7 L428.7 365.8 L427.1 364.3 L426.0 363.1 L425.5 362.2 L425.5 361.8 L425.9 360.8 L426.8 359.4 L428.1 357.6 L429.9 355.4 L432.0 352.2 L434.5 348.2 L437.4 343.4 L440.6 337.6 L443.3 333.3 L445.5 330.5ZM76.4 358.8 L77.1 358.7 L77.9 359.0 L78.9 359.8 L80.1 360.9 L81.4 362.6 L82.9 364.3 L84.5 366.3 L86.1 368.4 L87.9 370.6 L89.2 372.6 L90.1 374.2 L90.5 375.5 L90.5 376.5 L87.4 380.2 L81.2 386.7 L71.9 395.8 L59.6 407.7 L50.0 416.8 L43.2 423.1 L39.1 426.6 L37.9 427.4 L36.4 428.6 L34.6 430.2 L32.6 432.2 L30.4 434.8 L28.2 436.7 L26.2 438.1 L24.4 438.9 L22.6 439.1 L21.3 439.0 L20.3 438.6 L19.8 437.8 L19.7 436.7 L19.8 435.5 L20.1 434.3 L20.6 433.1 L21.4 431.9 L25.2 426.5 L32.2 417.0 L42.4 403.4 L55.6 385.6 L65.7 372.3 L72.7 363.3Z" ?>"/></clipPath></defs>
            <path class="lx-glow" d="<?= $markD ?>" pathLength="1000"/>
            <path class="lx-fill" d="<?= $markD ?>" fill-rule="evenodd"/>
            <g clip-path="url(#lxClip)"><rect class="lx-shine" x="-200" y="0" width="160" height="512" fill="url(#lxShine)"/></g>
            <path class="lx-line" d="<?= $markD ?>" pathLength="1000"/>
        </svg>
        <?php elseif ($logoStyle === 'glass'): ?>
        <div class="gl" id="auGlass" aria-hidden="true" style="--mk:url('<?= e(url('assets/img/logo-mark.png')) ?>')"><div class="gl-obj"><?php for ($gi = 9; $gi >= 1; $gi--): ?><i class="gl-l" style="--z:<?= $gi ?>"></i><?php endfor; ?><i class="gl-f"></i><i class="gl-spec"></i></div><span class="gl-shadow"></span></div>
        <?php endif; ?>
    </div>
    <h1 class="au-title" id="auTitle" data-text="<?= e($siteTitle) ?>"><?= e($siteTitle) ?></h1>
    <p class="au-tagline"><span id="auType"></span><i class="au-caret"></i></p>
    <button class="au-start" id="auStart" type="button">
        <span class="au-start-glow"></span>
        <span class="au-start-label">ورود و ثبت‌نام</span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
    </button>
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
                    <input type="text" name="identifier" id="auIdentifier" dir="ltr" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="موبایل، ایمیل یا نام کاربری" required>
                    <span class="au-id-mirror" id="auIdMirror" dir="ltr" aria-hidden="true"></span>
                    <em class="au-id-type" id="auIdType"></em>
                    <span class="au-id-line" aria-hidden="true"></span>
                    <span class="au-id-ring" aria-hidden="true"></span>
                    <span class="au-id-sparks" aria-hidden="true"></span>
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
                <button class="au-btn" type="submit"><span>ورود</span><i class="au-spin"></i><svg class="au-ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" pathLength="100"/></svg></button>
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
    <div class="au-rays" aria-hidden="true"></div>
    <div class="au-enter-core" id="auCore">
        <svg class="au-enter-rings" viewBox="0 0 200 200" aria-hidden="true">
            <circle class="e0" cx="100" cy="100" r="92"/>
            <circle class="ep" id="auProg" cx="100" cy="100" r="92" pathLength="100"/>
            <circle class="e2" cx="100" cy="100" r="78"/>
            <circle class="e3" cx="100" cy="100" r="66"/>
        </svg>
        <span class="au-enter-avatar" id="auEnterAvatar">N</span>
        <svg class="au-enter-check" viewBox="0 0 120 120" aria-hidden="true"><path d="M36 62 l16 16 l32 -34" pathLength="100"/></svg>
        <span class="au-enter-burst"></span><span class="au-enter-burst d2"></span>
    </div>
    <b class="au-enter-pct" id="auPct" dir="ltr">0%</b>
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
