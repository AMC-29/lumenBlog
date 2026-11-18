<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$tagStmt = $pdo->prepare('SELECT id, name, slug FROM tag WHERE slug = ?');
$tagStmt->execute([$slug]);
$tag = $tagStmt->fetch();
if (!$tag) {
    http_response_code(404);
    $pageTitle = 'Tag not found — Lumen';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h3>That tag isn’t here.</h3><p><a href="index.php">Back to all posts</a>.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$listing = fetch_post_page(
    $pdo,
    'blogPost p JOIN user u ON u.id = p.user_id JOIN post_tag pt ON pt.post_id = p.id JOIN tag t ON t.id = pt.tag_id',
    'p.status = ? AND t.slug = ?',
    ['published', $tag['slug']],
    requested_page($_GET['page'] ?? null),
    9,
    null,
    'latest',
    current_user_id()
);

$pageTitle = 'Posts tagged ' . $tag['name'] . ' — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="list-page-head">
    <a class="back-link" href="index.php">← All posts</a>
    <h1>Posts tagged <span class="tag-chip"><?= e($tag['name']) ?></span></h1>
</section>
<?php if (!$listing['posts']): ?>
    <div class="empty-state"><h3>No published posts with this tag yet.</h3></div>
<?php else: ?>
    <section class="post-grid">
        <?php foreach ($listing['posts'] as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?>
    </section>
<?php endif; ?>
<?php pagination_links($listing['page'], $listing['pages'], ['slug' => $tag['slug']]); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
