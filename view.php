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

if ($post['status'] === 'published' && !$isOwner) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $viewedPosts = $_SESSION['viewed_posts'] ?? [];
    if (!is_array($viewedPosts)) {
        $viewedPosts = [];
        $_SESSION['viewed_posts'] = [];
    }
    if (!preg_match('/bot|crawl|spider|preview/i', $userAgent)
        && !in_array((int) $post['id'], $viewedPosts, true)) {
        $increment = $pdo->prepare('UPDATE blogPost SET view_count = view_count + 1 WHERE id = ? AND status = ?');
        $increment->execute([(int) $post['id'], 'published']);
        $_SESSION['viewed_posts'][] = (int) $post['id'];
        $post['view_count'] = (int) $post['view_count'] + 1;
    }
}

$commentErrors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_comment'])) {
    if (!empty($_POST['website'] ?? '')) {
        header('Location: ' . post_url($post['slug']) . '#comments');
        exit;
    }
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = post_url($post['slug']) . '#comments';
        header('Location: login.php');
        exit;
    }
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $commentErrors[] = 'Your session expired. Please try again.';
    } elseif ($post['status'] !== 'published') {
        http_response_code(404);
        $commentErrors[] = 'Comments are only available on published posts.';
    } else {
        $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
        $length = mb_strlen($body, 'UTF-8');
        if ($length < 1 || $length > 1000) {
            $commentErrors[] = 'Comments must be between 1 and 1000 characters.';
        } else {
            $pdo->beginTransaction();
            try {
                $lockPost = $pdo->prepare('SELECT status FROM blogPost WHERE id = ? FOR UPDATE');
                $lockPost->execute([(int) $post['id']]);
                $currentPostStatus = $lockPost->fetchColumn();
                $lockUser = $pdo->prepare('SELECT id FROM user WHERE id = ? FOR UPDATE');
                $lockUser->execute([current_user_id()]);
                if ($currentPostStatus !== 'published') {
                    $pdo->rollBack();
                    $commentErrors[] = 'Comments are only available on published posts.';
                } else {
                    $rate = $pdo->prepare('SELECT COUNT(*) FROM comment WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
                    $rate->execute([current_user_id()]);
                    if ((int) $rate->fetchColumn() >= 5) {
                        $pdo->rollBack();
                        $commentErrors[] = 'You have reached the comment limit. Please try again in a few minutes.';
                    } else {
                        $insert = $pdo->prepare('INSERT INTO comment (post_id, user_id, body) VALUES (?, ?, ?)');
                        $insert->execute([(int) $post['id'], current_user_id(), $body]);
                        $pdo->commit();
                        flash('success', 'Comment added.');
                        header('Location: ' . post_url($post['slug']) . '#comments');
                        exit;
                    }
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$tagStmt = $pdo->prepare('SELECT t.name, t.slug FROM tag t JOIN post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
$tagStmt->execute([(int) $post['id']]);
$tags = $tagStmt->fetchAll();
$likeCountStmt = $pdo->prepare('SELECT COUNT(*) FROM post_like WHERE post_id = ?');
$likeCountStmt->execute([(int) $post['id']]);
$likeCount = (int) $likeCountStmt->fetchColumn();
$isLiked = false;
$isBookmarked = false;
if (is_logged_in()) {
    $likeStmt = $pdo->prepare('SELECT 1 FROM post_like WHERE post_id = ? AND user_id = ?');
    $likeStmt->execute([(int) $post['id'], current_user_id()]);
    $isLiked = (bool) $likeStmt->fetchColumn();
    $bookmarkStmt = $pdo->prepare('SELECT 1 FROM bookmark WHERE post_id = ? AND user_id = ?');
    $bookmarkStmt->execute([(int) $post['id'], current_user_id()]);
    $isBookmarked = (bool) $bookmarkStmt->fetchColumn();
}
$commentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM comment WHERE post_id = ? AND status = ?');
$commentCountStmt->execute([(int) $post['id'], 'visible']);
$commentTotal = (int) $commentCountStmt->fetchColumn();
$commentPages = max(1, (int) ceil($commentTotal / 20));
$commentPage = min(requested_page($_GET['comments_page'] ?? null), $commentPages);
$commentOffset = (int) (($commentPage - 1) * 20);
$commentStmt = $pdo->prepare(
    'SELECT c.id, c.user_id, c.body, c.created_at, u.username
     FROM comment c JOIN user u ON u.id = c.user_id
     WHERE c.post_id = ? AND c.status = ?
     ORDER BY c.created_at ASC, c.id ASC LIMIT 20 OFFSET ' . $commentOffset
);
$commentStmt->execute([(int) $post['id'], 'visible']);
$comments = $commentStmt->fetchAll();
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

    <?php if ($post['status'] === 'published'): ?>
        <div class="post-engagement">
            <button type="button" class="engagement-button like-button<?= $isLiked ? ' active' : '' ?>"
                    data-api="like" data-post-id="<?= (int) $post['id'] ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>">
                ♥ <span data-count><?= $likeCount ?></span> likes
            </button>
            <button type="button" class="engagement-button bookmark-button<?= $isBookmarked ? ' active' : '' ?>"
                    data-api="bookmark" data-post-id="<?= (int) $post['id'] ?>" aria-pressed="<?= $isBookmarked ? 'true' : 'false' ?>">
                <?= $isBookmarked ? 'Bookmarked' : 'Bookmark' ?>
            </button>
            <span class="view-count"><?= (int) $post['view_count'] ?> views</span>
        </div>
    <?php endif; ?>

    <div class="post-content"><?= markdown_to_html($post['content']) ?></div>

    <?php if ($post['status'] === 'published'): ?>
    <section class="comments-section" id="comments">
        <h2>Comments <span class="comment-total">(<?= $commentTotal ?>)</span></h2>
        <?php if (is_logged_in()): ?>
            <?php if ($commentErrors): ?><div class="form-errors"><?php foreach ($commentErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post" class="comment-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="honeypot" aria-hidden="true">Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <label for="commentBody">Add a comment</label>
                <textarea id="commentBody" name="body" maxlength="1000" required rows="4"><?= isset($_POST['submit_comment']) ? e(is_string($_POST['body'] ?? null) ? $_POST['body'] : '') : '' ?></textarea>
                <button class="btn btn-primary" type="submit" name="submit_comment" value="1">Comment</button>
            </form>
        <?php else: ?>
            <p><a href="login.php">Log in</a> to join the conversation.</p>
        <?php endif; ?>
        <div class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <article class="comment">
                    <header class="comment-header">
                        <strong><?= e($comment['username']) ?></strong>
                        <time datetime="<?= e($comment['created_at']) ?>"><?= e(format_date($comment['created_at'])) ?></time>
                    </header>
                    <p class="comment-body"><?= nl2br(e($comment['body'])) ?></p>
                    <?php if (is_logged_in()): ?>
                        <div class="comment-actions">
                            <?php if (current_user_id() === (int) $comment['user_id'] || $isOwner): ?>
                                <form method="post" action="comment-delete.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                    <button class="text-button" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                            <button class="text-button report-comment" type="button" data-comment-id="<?= (int) $comment['id'] ?>">Report</button>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php pagination_links($commentPage, $commentPages, ['slug' => $post['slug']], 'comments_page'); ?>
    </section>
    <?php endif; ?>
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
