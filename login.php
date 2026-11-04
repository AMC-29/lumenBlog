<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['username'] = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM user WHERE username = ? OR email = ?');
        $stmt->execute([$old['username'], $old['username']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Incorrect username/email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];

            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            flash('success', 'Welcome back, ' . $user['username'] . '!');
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$pageTitle = 'Log in — Lumen';
$bodyClass = 'auth-page login-page';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-wrap">
    <div class="auth-panel" aria-hidden="true">
        <div class="auth-panel-content">
            <?= brand_logo('brand-logo large') ?>
            <h2>Good to see you again.</h2>
            <p>Pick up right where you left off — your drafts and posts are waiting.</p>
        </div>
    </div>

    <div class="auth-form-side">
        <form class="auth-form" method="post" action="login.php" novalidate>
            <h1>Log in</h1>

            <?php if ($errors): ?>
                <div class="form-errors">
                    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label for="username">Username or email</label>
            <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn btn-primary btn-block">Log in</button>

            <p class="auth-switch">New to Lumen? <a href="register.php">Create an account</a></p>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
