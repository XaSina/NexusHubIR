<?php
/** @var array $post */
$tags = post_tags((int) $post['id']);
$related = $post['category_id'] ? get_posts(['category_id' => $post['category_id'], 'exclude' => [$post['id']], 'limit' => 4]) : [];
if (count($related) < 2) $related = get_posts(['exclude' => [$post['id']], 'limit' => 4]);
$reactions = setting_on('reactions_enabled') ? post_reactions((int) $post['id']) : [];
$myReaction = val('SELECT emoji FROM reaction_log WHERE post_id = ? AND ip_hash = ?', [$post['id'], ip_hash()]);
$comments = approved_comments((int) $post['id']);
$commentCount = (int) val("SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 'approved'", [$post['id']]);
$shareUrl = site_url('post/' . rawurlencode($post['slug']));
$shareText = $post['title'];
$prev = row(POST_SELECT . ' WHERE ' . pub_where() . ' AND p.published_at < ? ORDER BY p.published_at DESC LIMIT 1', [$post['published_at']]);
$next = row(POST_SELECT . ' WHERE ' . pub_where() . ' AND p.published_at > ? ORDER BY p.published_at ASC LIMIT 1', [$post['published_at']]);
$content = render_content((string) $post['content']);
$commentsOn = setting_on('comments_enabled') && $post['allow_comments'];
?>
<article class="container post-wrap" data-post="<?= (int) $post['id'] ?>">
    <nav class="breadcrumb" data-reveal>
        <a href="<?= e(url('')) ?>"><?= icon('home', 15) ?> خانه</a>
        <?php if ($post['cat_name']): ?><span>/</span><a href="<?= e(url('category/' . rawurlencode($post['cat_slug']))) ?>"><?= e($post['cat_name']) ?></a><?php endif; ?>
        <span>/</span><span class="bc-current"><?= e(mb_strimwidth($post['title'], 0, 50, '…')) ?></span>
    </nav>

    <header class="post-hero">
        <div class="post-cover parallax"><?= cover_html($post, '', true) ?></div>
        <div class="post-head glass" data-reveal>
            <div class="post-chips">
                <?php if ($post['cat_name']): ?><a class="chip" href="<?= e(url('category/' . rawurlencode($post['cat_slug']))) ?>" style="--c:<?= e($post['cat_color']) ?>"><?= e($post['cat_icon']) ?> <?= e($post['cat_name']) ?></a><?php endif; ?>
                <?php if ($post['is_featured']): ?><span class="chip chip-feat">⭐ ویژه</span><?php endif; ?>
                <?php if ($post['is_breaking']): ?><span class="chip chip-hot"><?= icon('bolt', 13) ?> فوری</span><?php endif; ?>
            </div>
            <h1 class="post-title"><?= e($post['title']) ?></h1>
            <?php if ($post['excerpt']): ?><p class="post-lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
            <div class="post-meta">
                <a class="pm-author" href="<?= e(url('author/' . rawurlencode((string) $post['author_username']))) ?>">
                    <?= user_avatar(['avatar' => $post['author_avatar'], 'display_name' => $post['author_name'], 'username' => $post['author_username']], 34) ?>
                    <span><?= e($post['author_name'] ?: $post['author_username'] ?: 'NEXUS') ?></span>
                </a>
                <span><?= icon('calendar', 15) ?> <?= jdate('l j F Y', $post['published_at']) ?></span>
                <span><?= icon('clock', 15) ?> <?= jdate('H:i', $post['published_at']) ?></span>
                <span><?= icon('sparkle', 15) ?> <?= fa_num((string) reading_time((string) $post['content'])) ?> دقیقه مطالعه</span>
                <span><?= icon('eye', 15) ?> <?= fa_short_num((int) $post['views'] + 1) ?> بازدید</span>
                <?php if ($commentsOn): ?><a href="#comments"><?= icon('comment', 15) ?> <?= fa_num((string) $commentCount) ?> نظر</a><?php endif; ?>
            </div>
        </div>
    </header>

    <div class="layout-2col post-layout">
        <div class="col-main">
            <?= ad_slot('post_top') ?>
            <div class="post-body glass" data-reveal>
                <div class="prose">
                    <?= $content ?>
                </div>

                <?php if ($post['source_name'] || $post['source_url']): ?>
                    <div class="post-source"><?= icon('link', 16) ?> منبع:
                        <?php if ($s = safe_link((string) $post['source_url'])): ?><a href="<?= e($s) ?>" target="_blank" rel="noopener nofollow"><?= e($post['source_name'] ?: parse_url($s, PHP_URL_HOST)) ?></a>
                        <?php else: ?><span><?= e($post['source_name']) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($tags): ?>
                    <div class="post-tags"><?= icon('tag', 16) ?>
                        <?php foreach ($tags as $t): ?><a href="<?= e(tag_url($t)) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($reactions): ?>
                    <div class="reactions" id="reactions" data-post="<?= (int) $post['id'] ?>">
                        <span class="react-label">نظرت درباره این خبر؟</span>
                        <div class="react-list">
                            <?php foreach ($reactions as $em => $cnt): ?>
                                <button class="react<?= $myReaction === $em ? ' mine' : '' ?>" data-emoji="<?= e($em) ?>">
                                    <span class="re-emoji"><?= e($em) ?></span><span class="re-count"><?= $cnt ? fa_num((string) $cnt) : '' ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="share">
                    <span class="share-label"><?= icon('share', 16) ?> اشتراک‌گذاری:</span>
                    <a class="share-btn tg" href="https://t.me/share/url?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener" aria-label="تلگرام"><?= icon('telegram', 18) ?></a>
                    <a class="share-btn wa" href="https://wa.me/?text=<?= rawurlencode($shareText . ' ' . $shareUrl) ?>" target="_blank" rel="noopener" aria-label="واتساپ"><?= icon('whatsapp', 18) ?></a>
                    <a class="share-btn xx" href="https://x.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener" aria-label="ایکس"><?= icon('x', 16) ?></a>
                    <button class="share-btn copy" data-copy="<?= e($shareUrl) ?>" aria-label="کپی لینک"><?= icon('copy', 17) ?></button>
                    <button class="share-btn native" data-share data-title="<?= e($shareText) ?>" data-url="<?= e($shareUrl) ?>" aria-label="اشتراک"><?= icon('send', 17) ?></button>
                </div>
            </div>

            <?= ad_slot('post_bottom') ?>

            <?php if ($prev || $next): ?>
            <nav class="post-nav" data-reveal>
                <?php if ($prev): ?><a class="pn glass" href="<?= e(post_url($prev)) ?>"><small><?= icon('chevron-right', 14) ?> خبر قبلی</small><b><?= e($prev['title']) ?></b></a><?php else: ?><span></span><?php endif; ?>
                <?php if ($next): ?><a class="pn pn-next glass" href="<?= e(post_url($next)) ?>"><small>خبر بعدی <?= icon('chevron-left', 14) ?></small><b><?= e($next['title']) ?></b></a><?php endif; ?>
            </nav>
            <?php endif; ?>

            <?php if ($related): ?>
            <section class="related">
                <div class="sec-head" data-reveal><h2 class="sec-title"><span class="sec-icon"><?= icon('grid', 20) ?></span> اخبار مرتبط</h2></div>
                <div class="grid-cards cols-2">
                    <?php foreach ($related as $i => $p) partial('card', ['p' => $p, 'i' => $i]); ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($commentsOn): ?>
            <section class="comments glass" id="comments" data-reveal>
                <h2 class="sec-title"><span class="sec-icon"><?= icon('comment', 20) ?></span> نظرات <small>(<?= fa_num((string) $commentCount) ?>)</small></h2>
                <div class="comment-list">
                    <?php if (!$comments): ?><p class="muted">اولین نفری باش که نظر می‌ده! 💬</p><?php endif; ?>
                    <?php foreach ($comments as $c): ?>
                        <div class="cmt" id="c<?= (int) $c['id'] ?>">
                            <div class="cmt-head">
                                <span class="avatar avatar-letter"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                                <b><?= e($c['name']) ?></b><?php if ($c['user_id']): ?><span class="cmt-badge">تیم NEXUS</span><?php endif; ?>
                                <small><?= jago($c['created_at']) ?></small>
                                <button class="cmt-reply" data-reply="<?= (int) $c['id'] ?>" data-name="<?= e($c['name']) ?>">پاسخ</button>
                            </div>
                            <p><?= nl2br(e($c['body'])) ?></p>
                            <?php foreach ($c['replies'] as $r): ?>
                                <div class="cmt cmt-child">
                                    <div class="cmt-head">
                                        <span class="avatar avatar-letter"><?= e(mb_substr($r['name'], 0, 1)) ?></span>
                                        <b><?= e($r['name']) ?></b><?php if ($r['user_id']): ?><span class="cmt-badge">تیم NEXUS</span><?php endif; ?>
                                        <small><?= jago($r['created_at']) ?></small>
                                    </div>
                                    <p><?= nl2br(e($r['body'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form class="comment-form" id="commentForm" novalidate>
                    <h3>ارسال نظر</h3>
                    <div class="reply-to" id="replyTo" hidden>در پاسخ به <b></b> <button type="button" aria-label="لغو"><?= icon('close', 14) ?></button></div>
                    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                    <input type="hidden" name="parent_id" value="0">
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <?php if (!current_user() && ($cm = current_member())): ?>
                    <div class="reply-to">✍️ نظر شما با نام <b><?= e(member_full_name($cm)) ?></b> ثبت می‌شود.</div>
                    <?php elseif (!current_user()): ?>
                    <div class="form-row">
                        <label class="field"><span>نام *</span><input name="name" required maxlength="60" placeholder="نام شما"></label>
                        <label class="field"><span>ایمیل (نمایش داده نمی‌شود)</span><input name="email" type="email" maxlength="190" placeholder="email@example.com" dir="ltr"></label>
                    </div>
                    <?php endif; ?>
                    <label class="field"><span>متن نظر *</span><textarea name="body" rows="4" required maxlength="2000" placeholder="نظرت رو بنویس..."></textarea></label>
                    <button class="btn btn-primary" type="submit"><?= icon('send', 17) ?> ارسال نظر</button>
                    <div class="form-msg" role="status"></div>
                </form>
            </section>
            <?php endif; ?>
        </div>
        <?php partial('sidebar'); ?>
    </div>
</article>
