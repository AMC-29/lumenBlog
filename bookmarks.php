<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$listing = fetch_post_page(
    $pdo,
    'blogPost p JOIN user u ON u.id = p.user_id JOIN bookmark b ON b.post_id = p.id',
    'p.status = ? AND b.user_id = ?',
    ['published', current_user_id()],
    requested_page($_GET['page'] ?? null),
    9,
    null,
    'latest',
    current_user_id()
);
$pageTitle = 'Bookmarks — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="list-page-head"><h1>Bookmarks</h1><p>Your saved published stories.</p></section>
<?php if (!$listing['posts']): ?>
    <div class="empty-state"><h3>No bookmarks yet.</h3><p>Save a story with its Bookmark button and it will appear here.</p></div>
<?php else: ?>
    <section class="post-grid"><?php foreach ($listing['posts'] as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?></section>
<?php endif; ?>
<?php pagination_links($listing['page'], $listing['pages']); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
