<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/markdown.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare(
    'SELECT p.*, u.username FROM blogPost p
     JOIN user u ON u.id = p.user_id
     WHERE p.slug = ?'
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post not found — Lumen';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h3>That story isn’t here.</h3><p>It may have been removed. <a href="index.php">Back to all posts</a>.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isOwner = is_logged_in() && current_user_id() === (int) $post['user_id'];
$pageTitle = $post['title'] . ' — Lumen';
require __DIR__ . '/includes/header.php';
?>

<article class="post-view">
    <a href="index.php" class="back-link">← All posts</a>

    <header class="post-view-header">
        <h1><?= e($post['title']) ?></h1>
        <div class="post-view-meta">
            <span class="avatar-badge"><?= e(initials($post['username'])) ?></span>
            <div>
                <div class="post-view-author"><?= e($post['username']) ?></div>
                <div class="post-view-date">
                    <?= format_date($post['created_at']) ?>
                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                        · edited <?= format_date($post['updated_at']) ?>
                    <?php endif; ?>
                    · <?= reading_time($post['content']) ?>
                </div>
            </div>

            <?php if ($isOwner): ?>
                <div class="post-owner-actions">
                    <a href="editor.php?id=<?= (int) $post['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                    <button type="button" class="btn btn-ghost btn-sm btn-danger" id="deleteBtn"
                            data-id="<?= (int) $post['id'] ?>" data-title="<?= e($post['title']) ?>">Delete</button>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="post-content">
        <?= markdown_to_html($post['content']) ?>
    </div>
</article>

<!-- Delete confirmation modal -->
<div class="modal-backdrop" id="deleteModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <h3 id="modalTitle">Delete this post?</h3>
        <p id="modalText">This can't be undone.</p>
        <form method="post" action="delete.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="deleteId" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" id="modalCancel">Cancel</button>
                <button type="submit" class="btn btn-danger-solid">Delete post</button>
            </div>
        </form>
    </div>
</div>

<?php $extraScripts = '<script src="js/post-view.js"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
