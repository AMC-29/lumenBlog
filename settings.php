<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/uploads.php';

require_login();
$userStmt = $pdo->prepare('SELECT id, username, bio, avatar FROM user WHERE id = ?');
$userStmt->execute([current_user_id()]);
$user = $userStmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$bio = $user['bio'];
$avatar = $user['avatar'];
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (post_body_exceeds_limit()) {
        $errors[] = 'The submitted form is larger than the server upload limit. Choose a smaller image and try again.';
    } elseif (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $bio = is_string($_POST['bio'] ?? null) ? trim($_POST['bio']) : '';
        if (mb_strlen($bio, 'UTF-8') > 500) {
            $errors[] = 'Bio must be 500 characters or fewer.';
        }

        $newAvatar = null;
        if (!$errors) {
            try {
                $newAvatar = save_image_upload($_FILES['avatar'] ?? null, 'avatars');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if (!$errors) {
            $removeAvatar = isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1';
            $updatedAvatar = $removeAvatar && $newAvatar === null ? null : ($newAvatar ?? $user['avatar']);
            try {
                $pdo->beginTransaction();
                $update = $pdo->prepare('UPDATE user SET bio = ?, avatar = ? WHERE id = ?');
                $update->execute([$bio, $updatedAvatar, current_user_id()]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newAvatar !== null) {
                    delete_image_file('avatars', $newAvatar);
                }
                if ($exception instanceof PDOException) {
                    throw $exception;
                }
                $errors[] = $exception->getMessage();
            }

            if (!$errors) {
                if ($updatedAvatar !== $user['avatar']) {
                    try {
                        delete_image_file('avatars', $user['avatar']);
                    } catch (RuntimeException $exception) {
                        flash('error', $exception->getMessage());
                    }
                }
                flash('success', 'Your profile settings were saved.');
                header('Location: settings.php');
                exit;
            }
            $avatar = $user['avatar'];
        }
    }
}

$avatarUrl = image_url('avatars', $avatar);
$pageTitle = 'Settings — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="settings-wrap">
    <h1>Profile settings</h1>
    <?php if ($errors): ?><div class="form-errors"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="settings.php" class="settings-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label for="bioInput">Bio (up to 500 characters)</label>
        <textarea id="bioInput" name="bio" maxlength="500" rows="6"><?= e($bio) ?></textarea>
        <label for="avatarInput">Avatar (JPEG, PNG, or WebP; max 4 MB)</label>
        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" data-image-input data-preview-target="avatarPreview">
        <div class="upload-preview" id="avatarPreview">
            <?php if ($avatarUrl): ?><img class="profile-avatar" src="<?= e($avatarUrl) ?>" alt="Current avatar"><?php endif; ?>
        </div>
        <?php if ($user['avatar']): ?><label class="inline-check"><input type="checkbox" name="remove_avatar" value="1"> Remove current avatar</label><?php endif; ?>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
</section>
<?php
$extraScripts = '<script src="js/image-upload.js?v=' . filemtime(__DIR__ . '/js/image-upload.js') . '"></script>';
require __DIR__ . '/includes/footer.php';
?>
