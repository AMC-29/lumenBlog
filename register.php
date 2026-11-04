<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['username'] = trim($_POST['username'] ?? '');
        $old['email']    = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm          = $_POST['confirm_password'] ?? '';

        if (!preg_match('/\A[A-Za-z0-9_.-]{3,50}\z/', $old['username'])) {
            $errors[] = 'Username must be 3–50 characters and use only letters, numbers, dots, underscores, or hyphens.';
        }
        if (mb_strlen($old['email']) > 150) {
            $errors[] = 'Email address must be no more than 150 characters.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM user WHERE username = ? OR email = ?');
            $stmt->execute([$old['username'], $old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'That username or email is already taken.';
            }
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO user (username, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$old['username'], $old['email'], $hash, 'author']);

            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $pdo->lastInsertId();
            $_SESSION['username'] = $old['username'];
            flash('success', 'Welcome to Lumen, ' . $old['username'] . '! Your account is ready.');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Sign up — Lumen';
$bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-wrap">
    <div class="auth-panel" aria-hidden="true">
        <div class="auth-panel-content">
            <span class="brand-mark large"></span>
            <h2>Where ideas dawn.</h2>
            <p>Join Lumen to write, edit, and share your own stories — all under your own name.</p>
        </div>
    </div>

    <div class="auth-form-side">
        <form class="auth-form" method="post" action="register.php" novalidate>
            <h1>Create your account</h1>

            <?php if ($errors): ?>
                <div class="form-errors">
                    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="150" autocomplete="email">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">

            <button type="submit" class="btn btn-primary btn-block">Sign up</button>

            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
