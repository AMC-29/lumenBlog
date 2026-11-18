<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$listing = fetch_post_page(
    $pdo,
    'blogPost p JOIN user u ON u.id = p.user_id',
    'p.status = ?',
    ['published'],
    requested_page($_GET['page'] ?? null),
    9,
    current_user_id(),
    'latest',
    current_user_id()
);
$suggestions = [];
if (!$listing['posts']) {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, COUNT(p.id) AS post_count
         FROM user u JOIN blogPost p ON p.user_id = u.id AND p.status = ?
         WHERE u.id != ? AND NOT EXISTS (
             SELECT 1 FROM `follow` f WHERE f.follower_id = ? AND f.author_id = u.id
         )
         GROUP BY u.id, u.username ORDER BY post_count DESC, u.username LIMIT 5'
    );
    $stmt->execute(['published', current_user_id(), current_user_id()]);
    $suggestions = $stmt->fetchAll();
}
$pageTitle = 'Following feed — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="list-page-head"><h1>Following feed</h1><p>New stories from authors you follow.</p></section>
<?php if (!$listing['posts']): ?>
    <div class="empty-state">
        <h3>Your feed is quiet.</h3>
        <?php if ($suggestions): ?>
            <p>Discover an author to follow:</p>
            <div class="suggested-authors"><?php foreach ($suggestions as $author): ?>
                <a class="tag-chip" href="<?= e(profile_url($author['username'])) ?>"><?= e($author['username']) ?> · <?= (int) $author['post_count'] ?> posts</a>
            <?php endforeach; ?></div>
        <?php else: ?><p>Visit an author profile and follow them to see their posts here.</p><?php endif; ?>
    </div>
<?php else: ?>
    <section class="post-grid"><?php foreach ($listing['posts'] as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?></section>
<?php endif; ?>
<?php pagination_links($listing['page'], $listing['pages']); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
