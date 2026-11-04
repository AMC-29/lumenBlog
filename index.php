<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT p.*, u.username FROM blogPost p
         JOIN user u ON u.id = p.user_id
         WHERE p.title LIKE ? OR p.content LIKE ?
         ORDER BY p.created_at DESC'
    );
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query(
        'SELECT p.*, u.username FROM blogPost p
         JOIN user u ON u.id = p.user_id
         ORDER BY p.created_at DESC'
    );
}

$posts = $stmt->fetchAll();

// Marquee only on the default (not searched) view, and only with 3+ posts.
$useMarquee = ($search === '' && count($posts) >= 3);

/** Print one post card. $opts: marquee (bool), clone (bool), delay (ms). */
function render_post_card(array $post, array $opts = []): void
{
    $marquee = !empty($opts['marquee']);
    $clone   = !empty($opts['clone']);
    $delay   = (int) ($opts['delay'] ?? 0);
    ?>
    <article class="post-card<?= $marquee ? '' : ' reveal' ?>"<?= $marquee ? '' : ' style="--delay: ' . $delay . 'ms"' ?>>
        <a href="view.php?slug=<?= urlencode($post['slug']) ?>" class="post-card-link"<?= $clone ? ' tabindex="-1"' : '' ?>>
            <div class="post-card-top"></div>
            <div class="post-card-body">
                <h2><?= e($post['title']) ?></h2>
                <p class="post-excerpt"><?= e(excerpt($post['content'])) ?></p>
            </div>
            <div class="post-card-meta">
                <span class="avatar-badge small"><?= e(initials($post['username'])) ?></span>
                <span><?= e($post['username']) ?></span>
                <span class="meta-dot">•</span>
                <span><?= format_date($post['created_at']) ?></span>
                <span class="meta-dot">•</span>
                <span><?= reading_time($post['content']) ?></span>
            </div>
        </a>
    </article>
    <?php
}

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
    // Repeat posts until one group is wide enough for big screens, then render
    // that group twice so the loop is seamless.
    $group = $posts;
    while (count($group) < 8) {
        $group = array_merge($group, $posts);
    }
    $duration = count($group) * 7; // ~7s per card = slow, constant speed
?>
<section class="marquee" aria-label="Latest posts" style="--marquee-duration: <?= $duration ?>s">
    <div class="marquee-track">
        <div class="marquee-group">
            <?php foreach ($group as $post): render_post_card($post, ['marquee' => true]); endforeach; ?>
        </div>
        <div class="marquee-group" aria-hidden="true">
            <?php foreach ($group as $post): render_post_card($post, ['marquee' => true, 'clone' => true]); endforeach; ?>
        </div>
    </div>
</section>

<?php else: ?>
<section class="post-grid" aria-live="polite">
    <?php foreach ($posts as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>