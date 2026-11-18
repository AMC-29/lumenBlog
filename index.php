<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = requested_page($_GET['page'] ?? null);
$where = 'p.status = ?';
$params = ['published'];
if ($search !== '') {
    $where .= ' AND (p.title LIKE ? ESCAPE \'!\' OR p.content LIKE ? ESCAPE \'!\')';
    $like = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $params[] = $like;
    $params[] = $like;
}

$listing = paginated_posts($pdo, 'blogPost p JOIN user u ON u.id = p.user_id', $where, $params, $page);
$posts = $listing['posts'];
$useMarquee = ($search === '' && $listing['page'] === 1 && count($posts) >= 3);
$popularTags = popular_tags($pdo, 8);

$pageTitle = 'Lumen — a blog for bright ideas';
$bodyClass = 'home-page';
require __DIR__ . '/includes/header.php';
?>

<section class="masthead">
    <h1>Stories worth <span class="accent-underline">the sunrise</span>.</h1>
    <p class="masthead-sub">A small, fast place to write and share your thoughts<br>"Where every draft finds its dawn."</p>
    <form class="search-bar" method="get" action="index.php" role="search">
        <input type="search" name="q" placeholder="Search posts by title or content…" value="<?= e($search) ?>">
        <button type="submit">Search</button>
    </form>
</section>

<?php if ($popularTags): ?>
    <section class="popular-tags home-popular-tags" aria-label="Popular tags">
        <strong>Popular tags</strong>
        <?php foreach ($popularTags as $tag): ?><a class="tag-chip" href="<?= e(tag_url($tag['slug'])) ?>"><?= e($tag['name']) ?></a><?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (!$posts): ?>
    <section class="post-grid" aria-live="polite">
        <div class="empty-state">
            <?php if ($search !== ''): ?>
                <h3>No posts match “<?= e($search) ?>”.</h3>
                <p>Try a different search term, or <a href="index.php">browse everything</a>.</p>
            <?php else: ?>
                <h3>No stories yet.</h3>
                <p><?= is_logged_in() ? '<a href="editor.php">Write the first one</a>.' : '<a href="register.php">Create an account</a> to publish the first one.' ?></p>
            <?php endif; ?>
        </div>
    </section>
<?php elseif ($useMarquee): ?>
    <?php
    $group = $posts;
    while (count($group) < 8) {
        $group = array_merge($group, $posts);
    }
    $duration = count($group) * 7;
    ?>
    <section class="marquee" aria-label="Latest posts" style="--marquee-duration: <?= (int) $duration ?>s">
        <div class="marquee-track">
            <div class="marquee-group"><?php foreach ($group as $post): render_post_card($post, ['marquee' => true]); endforeach; ?></div>
            <div class="marquee-group" aria-hidden="true"><?php foreach ($group as $post): render_post_card($post, ['marquee' => true, 'clone' => true]); endforeach; ?></div>
        </div>
    </section>
<?php else: ?>
    <section class="post-grid" aria-live="polite">
        <?php foreach ($posts as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?>
    </section>
<?php endif; ?>

<?php pagination_links($listing['page'], $listing['pages'], $search === '' ? [] : ['q' => $search]); ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
