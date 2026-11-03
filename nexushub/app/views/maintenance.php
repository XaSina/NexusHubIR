<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(setting('site_title')) ?> — به زودی</title>
<link rel="icon" href="<?= e(media_url((string) setting('favicon'))) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<style><?= theme_css_vars() ?></style>
</head>
<body class="page-maint">
<div class="bg-layer" aria-hidden="true"><div class="bg-blob b1"></div><div class="bg-blob b2"></div><div class="bg-grid"></div><div class="bg-vignette"></div></div>
<div class="maint glass">
    <div class="pl-core"><span class="pl-ring r1"></span><span class="pl-ring r2"></span><span class="pl-ring r3"></span><img src="<?= e(media_url((string) setting('logo'))) ?>" alt=""></div>
    <h1 class="glitch" data-text="<?= e(setting('site_title')) ?>"><?= e(setting('site_title')) ?></h1>
    <p><?= e(setting('maintenance_text')) ?></p>
    <?php if ($tg = safe_link((string) setting('social_telegram'))): ?><a class="btn btn-primary" href="<?= e($tg) ?>" target="_blank" rel="noopener"><?= icon('telegram', 18) ?> کانال تلگرام</a><?php endif; ?>
</div>
</body>
</html>
