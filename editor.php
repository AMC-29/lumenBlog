<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/uploads.php';

require_login();

$rawId = $_GET['id'] ?? null;
$postId = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;
$post = null;
if ($postId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM blogPost WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if (!$post) {
        flash('error', 'That post does not exist.');
        header('Location: index.php');
        exit;
    }
    if ((int) $post['user_id'] !== current_user_id()) {
        flash('error', 'You can only edit your own posts.');
        header('Location: ' . post_url($post['slug']));
        exit;
    }
}

$errors = [];
$title = $post['title'] ?? '';
$content = $post['content'] ?? '';
$tagInput = '';
if ($post) {
    $tagStmt = $pdo->prepare('SELECT t.name FROM tag t JOIN post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
    $tagStmt->execute([$postId]);
    $tagInput = implode(', ', array_column($tagStmt->fetchAll(), 'name'));
}
$coverFilename = $post['cover_image'] ?? null;
$intent = '';

$pendingDraft = $_SESSION['pending_editor_draft'] ?? null;
if (is_array($pendingDraft) && (int) ($pendingDraft['post_id'] ?? -1) === $postId) {
    $title = is_string($pendingDraft['title'] ?? null) ? $pendingDraft['title'] : $title;
    $content = is_string($pendingDraft['content'] ?? null) ? $pendingDraft['content'] : $content;
    unset($_SESSION['pending_editor_draft']);
    flash('info', 'Your unsaved post was restored. Review it and save again.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (post_body_exceeds_limit()) {
        $errors[] = 'The submitted form is larger than the server upload limit. Choose a smaller image and try again.';
    } elseif (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        $content = is_string($_POST['content'] ?? null) ? trim($_POST['content']) : '';
        $tagInput = is_string($_POST['tags'] ?? null) ? trim($_POST['tags']) : '';
        $intent = is_string($_POST['intent'] ?? null) ? $_POST['intent'] : '';
        $savingDraft = in_array($intent, ['save_draft', 'unpublish'], true);
        $publishing = in_array($intent, ['publish', 'save_changes'], true);

        if (!$savingDraft && !$publishing) {
            $errors[] = 'Choose whether to save a draft or publish the post.';
        }
        if ($title === '' || mb_strlen($title, 'UTF-8') > 200 || (!$savingDraft && mb_strlen($title, 'UTF-8') < 3)) {
            $errors[] = $savingDraft
                ? 'A title is required and must be no more than 200 characters.'
                : 'Title must be between 3 and 200 characters.';
        }
        if ($publishing && mb_strlen($content, 'UTF-8') < 10) {
            $errors[] = 'Post content must be at least 10 characters to publish.';
        }

        try {
            $tags = parse_post_tags($tagInput);
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
            $tags = [];
        }

        $newCover = null;
        if (!$errors) {
            try {
                $newCover = save_image_upload($_FILES['cover_image'] ?? null, 'covers');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (!$errors) {
            $removeCover = isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1';
            $oldCoverToDelete = null;
            try {
                $pdo->beginTransaction();
                if ($post) {
                    $lock = $pdo->prepare('SELECT * FROM blogPost WHERE id = ? AND user_id = ? FOR UPDATE');
                    $lock->execute([$postId, current_user_id()]);
                    $current = $lock->fetch();
                    if (!$current) {
                        throw new RuntimeException('You can only edit your own posts.');
                    }
                    $slug = $current['slug'];
                    if ($title !== $current['title']) {
                        $slug = make_slug($pdo, $title, $postId);
                        $deleteReturningSlug = $pdo->prepare('DELETE FROM slug_history WHERE post_id = ? AND slug = ?');
                        $deleteReturningSlug->execute([$postId, $slug]);
                        if ($current['status'] === 'published' && $slug !== $current['slug']) {
                            $rememberSlug = $pdo->prepare('INSERT INTO slug_history (post_id, slug) VALUES (?, ?)');
                            $rememberSlug->execute([$postId, $current['slug']]);
                        }
                    }

                    $status = $intent === 'unpublish' || $intent === 'save_draft' ? 'draft' : 'published';
                    $cover = $newCover ?? $current['cover_image'];
                    if ($removeCover && $newCover === null) {
                        $cover = null;
                    }
                    $oldCoverToDelete = $cover !== $current['cover_image'] ? $current['cover_image'] : null;
                    $publishedAt = $status === 'published'
                        ? ($current['published_at'] ?? date('Y-m-d H:i:s'))
                        : $current['published_at'];
                    $update = $pdo->prepare(
                        'UPDATE blogPost SET title = ?, slug = ?, content = ?, status = ?, published_at = ?, cover_image = ? ' .
                        'WHERE id = ? AND user_id = ?'
                    );
                    $update->execute([
                        $title, $slug, $content, $status, $publishedAt, $cover, $postId, current_user_id()
                    ]);
                    replace_post_tags($pdo, $postId, $tags);
                    $savedId = $postId;
                } else {
                    $slug = make_slug($pdo, $title);
                    $status = $intent === 'publish' ? 'published' : 'draft';
                    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
                    $cover = $removeCover && $newCover === null ? null : $newCover;
                    $insert = $pdo->prepare(
                        'INSERT INTO blogPost (user_id, title, slug, content, status, published_at, cover_image) ' .
                        'VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([current_user_id(), $title, $slug, $content, $status, $publishedAt, $cover]);
                    $savedId = (int) $pdo->lastInsertId();
                    replace_post_tags($pdo, $savedId, $tags);
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newCover !== null) {
                    delete_image_file('covers', $newCover);
                }
                if ($exception instanceof PDOException) {
                    throw $exception;
                }
                $errors[] = $exception->getMessage();
            }

            if (!$errors) {
                if ($oldCoverToDelete !== null) {
                    try {
                        delete_image_file('covers', $oldCoverToDelete);
                    } catch (RuntimeException $exception) {
                        flash('error', $exception->getMessage());
                    }
                }
                $_SESSION['clear_editor_draft'] = $post ? (string) $postId : 'new';
                flash('success', $status === 'published' ? 'Post published.' : 'Draft saved.');
                header('Location: ' . post_url($slug));
                exit;
            }
        }
    }
}

$popularTags = popular_tags($pdo);
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
        <div class="form-errors"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="editor.php<?= $post ? '?id=' . (int) $post['id'] : '' ?>" id="editorForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="text" name="title" id="titleInput" class="title-input" placeholder="Give your post a title…"
               value="<?= e($title) ?>" required maxlength="200" autocomplete="off">

        <label for="tagInput">Tags (comma-separated, up to 5)</label>
        <input type="text" name="tags" id="tagInput" value="<?= e($tagInput) ?>" maxlength="160" placeholder="e.g. writing, travel">
        <?php if ($popularTags): ?>
            <div class="popular-tags" aria-label="Popular tags">
                <?php foreach ($popularTags as $tag): ?>
                    <button class="tag-chip tag-chip-button" type="button" data-tag="<?= e($tag['name']) ?>"><?= e($tag['name']) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label for="coverInput">Cover image (JPEG, PNG, or WebP; max 4 MB)</label>
        <input type="file" id="coverInput" name="cover_image" accept="image/jpeg,image/png,image/webp" data-image-input data-preview-target="coverPreview">
        <div class="upload-preview" id="coverPreview">
            <?php $coverUrl = image_url('covers', $coverFilename); ?>
            <?php if ($coverUrl): ?><img src="<?= e($coverUrl) ?>" alt="Current cover image"><?php endif; ?>
        </div>
        <?php if ($coverFilename): ?>
            <label class="inline-check"><input type="checkbox" name="remove_cover" value="1"> Remove current cover image</label>
        <?php endif; ?>

        <div class="editor-panes">
            <div class="pane pane-write active" data-pane="write">
                <textarea name="content" id="contentInput" placeholder="Write in Markdown — **bold**, *italic*, # headings, - lists, `code`, [links](https://…)"><?= e($content) ?></textarea>
                <div class="editor-meta"><span id="wordCount">0 words</span><span id="draftStatus" class="draft-status"></span></div>
            </div>
            <div class="pane pane-preview" data-pane="preview">
                <div class="markdown-preview" id="previewOutput"><p class="preview-placeholder">Your preview will appear here as you type…</p></div>
            </div>
        </div>

        <div class="editor-actions">
            <a href="<?= e($post ? post_url($post['slug']) : 'index.php') ?>" class="btn btn-ghost">Cancel</a>
            <?php if ($post && $post['status'] === 'published'): ?>
                <button type="submit" name="intent" value="unpublish" class="btn btn-ghost">Unpublish</button>
                <button type="submit" name="intent" value="save_changes" class="btn btn-primary">Save changes</button>
            <?php else: ?>
                <button type="submit" name="intent" value="save_draft" class="btn btn-ghost">Save draft</button>
                <button type="submit" name="intent" value="publish" class="btn btn-primary">Publish</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php
$extraScripts = '<script src="js/editor.js?v=' . filemtime(__DIR__ . '/js/editor.js') . '"></script>' .
    '<script src="js/image-upload.js?v=' . filemtime(__DIR__ . '/js/image-upload.js') . '"></script>';
?>
<?php require __DIR__ . '/includes/footer.php'; ?>
