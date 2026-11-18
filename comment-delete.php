<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_valid($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid request.');
    header('Location: index.php');
    exit;
}
$commentId = is_string($_POST['comment_id'] ?? null) && ctype_digit($_POST['comment_id'])
    ? (int) $_POST['comment_id'] : 0;
$stmt = $pdo->prepare(
    'SELECT c.id, c.user_id, p.user_id AS post_owner, p.slug
     FROM comment c JOIN blogPost p ON p.id = c.post_id WHERE c.id = ?'
);
$stmt->execute([$commentId]);
$comment = $stmt->fetch();
if (!$comment) {
    flash('error', 'That comment no longer exists.');
    header('Location: index.php');
    exit;
}
if ((int) $comment['user_id'] !== current_user_id() && (int) $comment['post_owner'] !== current_user_id()) {
    flash('error', 'You cannot delete this comment.');
    header('Location: ' . post_url($comment['slug']));
    exit;
}
$delete = $pdo->prepare('DELETE FROM comment WHERE id = ?');
$delete->execute([$commentId]);
flash('success', 'Comment deleted.');
header('Location: ' . post_url($comment['slug']) . '#comments');
exit;
