<?php
require_once __DIR__ . '/bootstrap.php';

try {
    api_require_post();
    $followerId = api_require_login();
    api_require_csrf();
    $value = $_POST['author_id'] ?? null;
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
        api_response(422, ['ok' => false, 'error' => 'Invalid author.']);
    }
    $authorId = (int) $value;
    if ($followerId === $authorId) {
        api_response(422, ['ok' => false, 'error' => 'You cannot follow yourself.']);
    }
    $author = $pdo->prepare('SELECT id FROM user WHERE id = ?');
    $author->execute([$authorId]);
    if (!$author->fetch()) {
        api_response(422, ['ok' => false, 'error' => 'Author is unavailable.']);
    }

    $exists = $pdo->prepare('SELECT 1 FROM `follow` WHERE follower_id = ? AND author_id = ?');
    $exists->execute([$followerId, $authorId]);
    $active = (bool) $exists->fetchColumn();
    if ($active) {
        $toggle = $pdo->prepare('DELETE FROM `follow` WHERE follower_id = ? AND author_id = ?');
        $toggle->execute([$followerId, $authorId]);
        $active = false;
    } else {
        $toggle = $pdo->prepare('INSERT IGNORE INTO `follow` (follower_id, author_id) VALUES (?, ?)');
        $toggle->execute([$followerId, $authorId]);
        $active = true;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM `follow` WHERE author_id = ?');
    $count->execute([$authorId]);
    api_response(200, ['ok' => true, 'active' => $active, 'count' => (int) $count->fetchColumn()]);
} catch (Throwable $exception) {
    api_fail($exception);
}
