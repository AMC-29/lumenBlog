<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$postId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = null;

if ($postId) {
    $stmt = $pdo->prepare('SELECT * FROM blogPost WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        flash('error', 'That post does not exist.');
        header('Location: index.php');
        exit;
    }
    if ((int) $post['user_id'] !== current_user_id()) {
        flash('error', "You can only edit your own posts.");
        header('Location: view.php?slug=' . urlencode($post['slug']));
        exit;
    }
}

$errors = [];
$title = $post['title'] ?? '';
$content = $post['content'] ?? '';

$pendingDraft = $_SESSION['pending_editor_draft'] ?? null;
if (is_array($pendingDraft) && (int) ($pendingDraft['post_id'] ?? -1) === $postId) {
    $title = is_string($pendingDraft['title'] ?? null) ? $pendingDraft['title'] : $title;
    $content = is_string($pendingDraft['content'] ?? null) ? $pendingDraft['content'] : $content;
    unset($_SESSION['pending_editor_draft']);
    flash('info', 'Your unsaved post was restored. Review it and save again.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            $errors[] = 'Title must be between 3 and 200 characters.';
        }
        if ($content === '' || mb_strlen($content) < 10) {
            $errors[] = 'Post content must be at least 10 characters.';
        }

        if (!$errors) {
            if ($post) {
                // Update — ownership already verified above.
                $slug = ($title !== $post['title']) ? make_slug($pdo, $title, $post['id']) : $post['slug'];
                $stmt = $pdo->prepare('UPDATE blogPost SET title = ?, slug = ?, content = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$title, $slug, $content, $post['id'], current_user_id()]);
                flash('success', 'Post updated.');
            } else {
                $slug = make_slug($pdo, $title);
                $stmt = $pdo->prepare('INSERT INTO blogPost (user_id, title, slug, content) VALUES (?, ?, ?, ?)');
                $stmt->execute([current_user_id(), $title, $slug, $content]);
                flash('success', 'Post published!');
            }
            $_SESSION['clear_editor_draft'] = $post ? (string) $post['id'] : 'new';
            header('Location: view.php?slug=' . urlencode($slug));
            exit;
        }
    }
}

$pageTitle = ($post ? 'Edit post' : 'New post') . ' — Lumen';
$bodyClass = 'editor-page';
require __DIR__ . '/includes/header.php';
?>

<section class="editor-wrap">
    <div class="editor-head">
        <h1><?= $post ? 'Edit post' : 'Write a new post' ?></h1>
        <div class="editor-tabs" role="tablist">
            <button type="button" class="tab-btn active" data-tab="write" role="tab" aria-selected="true">Write</button>
            <button type="button" class="tab-btn" data-tab="preview" role="tab" aria-selected="false">Preview</button>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="form-errors">
            <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="editor.php<?= $post ? '?id=' . (int) $post['id'] : '' ?>" id="editorForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <input type="text" name="title" id="titleInput" class="title-input" placeholder="Give your post a title…"
               value="<?= e($title) ?>" required minlength="3" maxlength="200" autocomplete="off">

        <div class="editor-panes">
            <div class="pane pane-write active" data-pane="write">
                <textarea name="content" id="contentInput" placeholder="Write in Markdown — **bold**, *italic*, # headings, - lists, `code`, [links](https://…)"
                          required minlength="10"><?= e($content) ?></textarea>
                <div class="editor-meta">
                    <span id="wordCount">0 words</span>
                    <span id="draftStatus" class="draft-status"></span>
                </div>
            </div>
            <div class="pane pane-preview" data-pane="preview">
                <div class="markdown-preview" id="previewOutput">
                    <p class="preview-placeholder">Your preview will appear here as you type…</p>
                </div>
            </div>
        </div>

        <div class="editor-actions">
            <a href="<?= $post ? 'view.php?slug=' . urlencode($post['slug']) : 'index.php' ?>" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= $post ? 'Save changes' : 'Publish post' ?></button>
        </div>
    </form>
</section>

<?php $extraScripts = '<script src="js/editor.js?v=' . filemtime(__DIR__ . '/js/editor.js') . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
