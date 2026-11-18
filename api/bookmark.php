<?php
require_once __DIR__ . '/bootstrap.php';

try {
    api_require_post();
    $userId = api_require_login();
    api_require_csrf();
    $postId = api_post_id();
    $published = $pdo->prepare('SELECT id FROM blogPost WHERE id = ? AND status = ?');
    $published->execute([$postId, 'published']);
    if (!$published->fetch()) {
        api_response(422, ['ok' => false, 'error' => 'Post is unavailable.']);
    }

    $exists = $pdo->prepare('SELECT 1 FROM bookmark WHERE post_id = ? AND user_id = ?');
    $exists->execute([$postId, $userId]);
    $active = (bool) $exists->fetchColumn();
    if ($active) {
        $toggle = $pdo->prepare('DELETE FROM bookmark WHERE post_id = ? AND user_id = ?');
        $toggle->execute([$postId, $userId]);
        $active = false;
    } else {
        $toggle = $pdo->prepare('INSERT IGNORE INTO bookmark (post_id, user_id) VALUES (?, ?)');
        $toggle->execute([$postId, $userId]);
        $active = true;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM bookmark WHERE post_id = ?');
    $count->execute([$postId]);
    api_response(200, ['ok' => true, 'active' => $active, 'count' => (int) $count->fetchColumn()]);
} catch (Throwable $exception) {
    api_fail($exception);
}
