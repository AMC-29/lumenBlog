<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/uploads.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid($_POST['csrf_token'] ?? null)) {
    flash('error', 'Invalid request.');
    header('Location: index.php');
    exit;
}

$rawId = $_POST['id'] ?? null;
$id = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;

$stmt = $pdo->prepare('SELECT * FROM blogPost WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    flash('error', 'That post no longer exists.');
    header('Location: index.php');
    exit;
}

if ((int) $post['user_id'] !== current_user_id()) {
    flash('error', 'You can only delete your own posts.');
    header('Location: ' . post_url($post['slug']));
    exit;
}

$cover = $post['cover_image'];
$stmt = $pdo->prepare('DELETE FROM blogPost WHERE id = ? AND user_id = ?');
$stmt->execute([$id, current_user_id()]);

try {
    delete_image_file('covers', $cover);
} catch (RuntimeException $exception) {
    flash('error', $exception->getMessage());
}
flash('success', 'Post deleted.');
header('Location: index.php');
exit;
