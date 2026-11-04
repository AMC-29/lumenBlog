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

$pageTitle = 'Lumen — a blog for bright ideas';
require __DIR__ . '/includes/header.php';
?>

<section class="masthead">
    <h1>Stories worth <span class="accent-underline">the sunrise</span>.</h1>
    <p class="masthead-sub">A small, fast place to write things down and share them — built by you, running on your own server.</p>

    <form class="search-bar" method="get" action="index.php" role="search">
        <input type="search" name="q" placeholder="Search posts by title or content…" value="<?= e($search) ?>">
        <button type="submit">Search</button>
    </form>
</section>

<section class="post-grid" aria-live="polite">
<?php if (!$posts): ?>
    <div class="empty-state">
        <?php if ($search !== ''): ?>
            <h3>No posts match “<?= e($search) ?>”.</h3>
            <p>Try a different search term, or <a href="index.php">browse everything</a>.</p>
        <?php else: ?>
            <h3>No stories yet.</h3>
            <p><?= is_logged_in() ? '<a href="editor.php">Write the first one</a>.' : '<a href="register.php">Create an account</a> to publish the first one.' ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($posts as $i => $post): ?>
        <article class="post-card reveal" style="--delay: <?= min($i, 8) * 60 ?>ms">
            <a href="view.php?slug=<?= urlencode($post['slug']) ?>" class="post-card-link">
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
    <?php endforeach; ?>
<?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
