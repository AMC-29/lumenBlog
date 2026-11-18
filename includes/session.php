<?php
/**
 * Session bootstrap + authentication helpers.
 * Include this at the very top of every page, before any HTML output.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Is a user currently logged in? */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** Get the logged-in user's id, or null. */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/** Get the logged-in user's display name, or null. */
function current_username(): ?string
{
    return $_SESSION['username'] ?? null;
}

/** Redirect to login.php if not authenticated, remembering where they came from. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && basename($requestPath ?: '') === 'editor.php') {
            $_SESSION['pending_editor_draft'] = [
                'post_id' => is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int) $_GET['id'] : 0,
                'title' => is_string($_POST['title'] ?? null) ? $_POST['title'] : '',
                'content' => is_string($_POST['content'] ?? null) ? $_POST['content'] : '',
            ];
        }
        flash('error', 'Please log in to continue.');
        header('Location: login.php');
        exit;
    }
}

/** Store a one-time flash message to show on the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pop and return all pending flash messages (used once, then cleared). */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Generate (or reuse) a CSRF token for the current session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Validate a submitted CSRF token. */
function csrf_valid($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
