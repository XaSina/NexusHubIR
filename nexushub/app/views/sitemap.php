<?php
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$posts = rows('SELECT slug, title, cover, updated_at, published_at FROM posts p WHERE ' . pub_where() . ' ORDER BY published_at DESC LIMIT 5000');
$img = fn($c) => $c && !str_ends_with(strtolower((string) $c), '.svg') ? '<image:image><image:loc>' . e(site_url($c)) . '</image:loc></image:image>' : '';
$lm = fn($d) => $d ? '<lastmod>' . date('c', strtotime((string) $d)) . '</lastmod>' : '';
$newest = $posts[0]['published_at'] ?? null;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<url><loc><?= e(site_url('')) ?></loc><?= $lm($newest) ?><changefreq>hourly</changefreq><priority>1.0</priority></url>
<url><loc><?= e(site_url('archive')) ?></loc><?= $lm($newest) ?><changefreq>hourly</changefreq><priority>0.8</priority></url>
<?php foreach (get_categories() as $c): ?><url><loc><?= e(site_url('category/' . rawurlencode($c['slug']))) ?></loc><changefreq>daily</changefreq><priority>0.7</priority></url>
<?php endforeach; ?>
<?php foreach (rows("SELECT slug, updated_at FROM pages WHERE status = 'published'") as $pg): ?><url><loc><?= e(site_url('page/' . rawurlencode($pg['slug']))) ?></loc><?= $lm($pg['updated_at']) ?><priority>0.4</priority></url>
<?php endforeach; ?>
<?php if (shop_on()): ?><url><loc><?= e(site_url('shop')) ?></loc><changefreq>daily</changefreq><priority>0.7</priority></url>
<?php foreach (rows("SELECT slug, image, updated_at FROM products WHERE status IN ('active','soldout')") as $pr): ?><url><loc><?= e(site_url('shop/' . rawurlencode($pr['slug']))) ?></loc><?= $lm($pr['updated_at']) ?><priority>0.6</priority><?= $img($pr['image']) ?></url>
<?php endforeach; endif; ?>
<?php foreach ($posts as $p): ?><url><loc><?= e(site_url('post/' . rawurlencode($p['slug']))) ?></loc><?= $lm($p['updated_at']) ?><?= $img($p['cover']) ?></url>
<?php endforeach; ?>
</urlset>
