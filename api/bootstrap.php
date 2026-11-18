<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/session.php';

function api_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        api_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
    }
}

function api_require_login(): int
{
    if (!is_logged_in()) {
        api_response(401, ['ok' => false, 'error' => 'Please log in to continue.']);
    }
    return (int) current_user_id();
}

function api_require_csrf(): void
{
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $bodyToken = $_POST['csrf_token'] ?? null;
    if (!csrf_valid(is_string($headerToken) ? $headerToken : $bodyToken)) {
        api_response(403, ['ok' => false, 'error' => 'Invalid request token.']);
    }
}

function api_post_id(): int
{
    $value = $_POST['post_id'] ?? null;
    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
        api_response(422, ['ok' => false, 'error' => 'Invalid post.']);
    }
    return (int) $value;
}

function api_fail(Throwable $exception): void
{
    error_log('API request failed: ' . $exception->getMessage());
    api_response(500, ['ok' => false, 'error' => 'A server error occurred.']);
}
