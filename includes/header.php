<?php
// Expects $pageTitle to be set by the including page. Falls back if not.
$pageTitle = $pageTitle ?? 'Lumen — a blog for bright ideas';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
<link rel="icon" type="image/png" href="images/logo-Photoroom.png">
</head>
<body class="<?= e($bodyClass) ?>">

<div class="horizon-bar" aria-hidden="true"></div>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="brand">
            <?= brand_logo() ?>
            <span class="brand-name">Lumen</span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="siteNav">
            <a href="index.php">Home</a>
            <?php if (is_logged_in()): ?>
                <a href="<?= e(profile_url((string) current_username())) ?>">My profile</a>
                <a href="drafts.php">My drafts</a>
                <a href="settings.php">Settings</a>
                <a href="editor.php" class="nav-cta">+ New post</a>
                <span class="nav-user">
                    <span class="avatar-badge"><?= e(initials(current_username())) ?></span>
                    <?= e(current_username()) ?>
                </span>
                <a href="logout.php" class="nav-link-muted nav-logout" id="logoutLink">Log out</a>
            <?php else: ?>
                <a href="login.php">Log in</a>
                <a href="register.php" class="nav-cta">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<?php
$clearEditorDraft = $_SESSION['clear_editor_draft'] ?? null;
unset($_SESSION['clear_editor_draft']);
?>
<script>
  window.__clearEditorDraft = <?= json_encode($clearEditorDraft, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  window.__flashes = <?= json_encode(get_flashes(), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
</script>

<main class="page">
