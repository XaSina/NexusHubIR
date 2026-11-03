<section class="container err-page">
    <div class="err-box glass" data-reveal>
        <div class="err-code glitch" data-text="404">404</div>
        <h1>اینجا چیزی پیدا نشد!</h1>
        <p>صفحه‌ای که دنبالش بودی وجود نداره یا جابه‌جا شده 🛸</p>
        <form class="ph-search" action="<?= e(url('search')) ?>" method="get">
            <?= icon('search', 18) ?><input type="search" name="q" placeholder="جستجو در اخبار..." minlength="2" required>
            <button class="btn btn-primary btn-sm" type="submit">جستجو</button>
        </form>
        <a class="btn btn-ghost" href="<?= e(url('')) ?>"><?= icon('home', 18) ?> بازگشت به خانه</a>
    </div>
</section>
