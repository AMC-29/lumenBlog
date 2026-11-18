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
<link rel="alternate" type="application/rss+xml" title="Lumen RSS" href="<?= e(rtrim(SITE_URL, '/') . '/rss.xml') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body class="<?= e($bodyClass) ?>">

<div class="horizon-bar" aria-hidden="true"></div>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="brand">
            <?= brand_logo() ?>
            <span class="brand-name">Lumen</span>
        </a>

        <nav class="site-nav" id="siteNav">
            <a href="index.php">Home</a>
            <?php if (is_logged_in()): ?>
                <a href="editor.php" class="nav-cta">+ New post</a>
                <button type="button" class="nav-user account-menu-trigger" id="accountMenuTrigger"
                        aria-controls="accountDrawer" aria-expanded="false" aria-label="Open account menu">
                    <span class="avatar-badge"><?= e(initials(current_username())) ?></span>
                    <span><?= e(current_username()) ?></span>
                </button>
                <a href="logout.php" class="nav-link-muted nav-logout" id="logoutLink">Log out</a>
            <?php else: ?>
                <a href="login.php">Log in</a>
                <a href="register.php" class="nav-cta">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if (is_logged_in()): ?>
<div class="account-drawer-backdrop" id="accountDrawerBackdrop" hidden></div>
<aside class="account-drawer" id="accountDrawer" aria-labelledby="accountDrawerTitle" aria-modal="true" role="dialog" tabindex="-1" hidden>
    <div class="account-drawer-header">
        <div class="account-drawer-user">
            <span class="avatar-badge"><?= e(initials(current_username())) ?></span>
            <div>
                <h2 id="accountDrawerTitle"><?= e(current_username()) ?></h2>
                <span>Account menu</span>
            </div>
        </div>
        <button type="button" class="account-drawer-close" id="accountDrawerClose" aria-label="Close account menu">&times;</button>
    </div>
    <nav class="account-drawer-nav" aria-label="Account navigation">
        <a href="feed.php">Following feed</a>
        <a href="bookmarks.php">Bookmarks</a>
        <a href="<?= e(profile_url((string) current_username())) ?>">My profile</a>
        <a href="drafts.php">My drafts</a>
        <a href="settings.php">Settings</a>
    </nav>
</aside>
<?php endif; ?>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<?php
$clearEditorDraft = $_SESSION['clear_editor_draft'] ?? null;
unset($_SESSION['clear_editor_draft']);
?>
<script>
  window.__clearEditorDraft = <?= json_encode($clearEditorDraft, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  window.__flashes = <?= json_encode(get_flashes(), JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  window.__loggedIn = <?= is_logged_in() ? 'true' : 'false' ?>;
</script>

<main class="page">
