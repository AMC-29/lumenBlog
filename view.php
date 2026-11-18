<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/markdown.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$selectPost = $pdo->prepare(
    'SELECT p.*, u.username, u.avatar AS author_avatar FROM blogPost p
     JOIN user u ON u.id = p.user_id WHERE p.slug = ?'
);
$selectPost->execute([$slug]);
$post = $selectPost->fetch();

if (!$post) {
    $history = $pdo->prepare(
        'SELECT p.*, u.username, u.avatar AS author_avatar FROM slug_history h
         JOIN blogPost p ON p.id = h.post_id
         JOIN user u ON u.id = p.user_id WHERE h.slug = ?'
    );
    $history->execute([$slug]);
    $oldPost = $history->fetch();
    $oldPostOwner = $oldPost && is_logged_in() && current_user_id() === (int) $oldPost['user_id'];
    if ($oldPost && ($oldPost['status'] === 'published' || $oldPostOwner)) {
        header('Location: ' . post_url($oldPost['slug']), true, 301);
        exit;
    }

    http_response_code(404);
    $pageTitle = 'Post not found — Lumen';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h3>That story isn’t here.</h3><p>It may have been removed. <a href="index.php">Back to all posts</a>.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isOwner = is_logged_in() && current_user_id() === (int) $post['user_id'];
if ($post['status'] !== 'published' && !$isOwner) {
    http_response_code(404);
    $pageTitle = 'Post not found — Lumen';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h3>That story isn’t here.</h3><p>It may have been removed. <a href="index.php">Back to all posts</a>.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tagStmt = $pdo->prepare('SELECT t.name, t.slug FROM tag t JOIN post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
$tagStmt->execute([(int) $post['id']]);
$tags = $tagStmt->fetchAll();
$coverUrl = image_url('covers', $post['cover_image']);
$authorAvatar = image_url('avatars', $post['author_avatar']);
$pageTitle = $post['title'] . ' — Lumen';
require __DIR__ . '/includes/header.php';
?>

<article class="post-view">
    <a href="index.php" class="back-link">← All posts</a>
    <?php if ($post['status'] === 'draft'): ?><div class="draft-banner">Draft preview — only you can see this unpublished post.</div><?php endif; ?>
    <?php if ($coverUrl): ?><img class="post-cover" src="<?= e($coverUrl) ?>" alt="<?= e($post['title']) ?>"><?php endif; ?>
    <header class="post-view-header">
        <h1><?= e($post['title']) ?></h1>
        <?php if ($tags): ?>
            <div class="post-tags"><?php foreach ($tags as $tag): ?><a class="tag-chip" href="<?= e(tag_url($tag['slug'])) ?>"><?= e($tag['name']) ?></a><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="post-view-meta">
            <?php if ($authorAvatar): ?><img class="avatar-image" src="<?= e($authorAvatar) ?>" alt=""><?php else: ?><span class="avatar-badge"><?= e(initials($post['username'])) ?></span><?php endif; ?>
            <div>
                <a class="post-view-author" href="<?= e(profile_url($post['username'])) ?>"><?= e($post['username']) ?></a>
                <div class="post-view-date">
                    <?= e(format_date($post['published_at'] ?? $post['created_at'])) ?>
                    <?php if ($post['updated_at'] !== $post['created_at']): ?> · edited <?= e(format_date($post['updated_at'])) ?><?php endif; ?>
                    · <?= e(reading_time($post['content'])) ?>
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

    <div class="post-content"><?= markdown_to_html($post['content']) ?></div>
</article>

<?php if ($isOwner): ?>
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
<?php endif; ?>

<?php $extraScripts = '<script src="js/post-view.js?v=' . filemtime(__DIR__ . '/js/post-view.js') . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
