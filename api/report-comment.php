<?php
require_once __DIR__ . '/bootstrap.php';

try {
    api_require_post();
    $userId = api_require_login();
    api_require_csrf();
    $rawId = $_POST['comment_id'] ?? null;
    $reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';
    if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1 || mb_strlen($reason, 'UTF-8') < 1 || mb_strlen($reason, 'UTF-8') > 255) {
        api_response(422, ['ok' => false, 'error' => 'Select a reason to report this comment.']);
    }
    $comment = $pdo->prepare(
        'SELECT c.id FROM comment c JOIN blogPost p ON p.id = c.post_id
         WHERE c.id = ? AND c.status = ? AND p.status = ?'
    );
    $comment->execute([(int) $rawId, 'visible', 'published']);
    if (!$comment->fetch()) {
        api_response(422, ['ok' => false, 'error' => 'Comment is unavailable.']);
    }
    $insert = $pdo->prepare('INSERT IGNORE INTO comment_report (comment_id, user_id, reason) VALUES (?, ?, ?)');
    $insert->execute([(int) $rawId, $userId, $reason]);
    api_response(200, ['ok' => true, 'active' => true, 'count' => 0]);
} catch (Throwable $exception) {
    api_fail($exception);
}
