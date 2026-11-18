<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$listing = paginated_posts(
    $pdo,
    'blogPost p JOIN user u ON u.id = p.user_id',
    'p.status = ? AND p.user_id = ?',
    ['draft', current_user_id()],
    requested_page($_GET['page'] ?? null)
);

$pageTitle = 'My drafts — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="list-page-head">
    <h1>My drafts</h1>
    <p>Only you can see these unpublished stories.</p>
</section>
<?php if (!$listing['posts']): ?>
    <div class="empty-state"><h3>No drafts yet.</h3><p><a href="editor.php">Start writing</a>.</p></div>
<?php else: ?>
    <section class="post-grid">
        <?php foreach ($listing['posts'] as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?>
    </section>
<?php endif; ?>
<?php pagination_links($listing['page'], $listing['pages']); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
