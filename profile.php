<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

$username = is_string($_GET['u'] ?? null) ? $_GET['u'] : '';
$userStmt = $pdo->prepare('SELECT id, username, bio, avatar, created_at FROM user WHERE username = ?');
$userStmt->execute([$username]);
$profile = $userStmt->fetch();
if (!$profile) {
    http_response_code(404);
    $pageTitle = 'Profile not found — Lumen';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h3>That profile isn’t here.</h3><p><a href="index.php">Back to all posts</a>.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$listing = paginated_posts(
    $pdo,
    'blogPost p JOIN user u ON u.id = p.user_id',
    'p.status = ? AND p.user_id = ?',
    ['published', (int) $profile['id']],
    requested_page($_GET['page'] ?? null)
);
$avatarUrl = image_url('avatars', $profile['avatar']);
$pageTitle = $profile['username'] . ' — Lumen';
require __DIR__ . '/includes/header.php';
?>
<section class="profile-head">
    <?php if ($avatarUrl): ?>
        <img class="profile-avatar" src="<?= e($avatarUrl) ?>" alt="<?= e($profile['username']) ?>">
    <?php else: ?>
        <span class="avatar-badge profile-avatar-fallback"><?= e(initials($profile['username'])) ?></span>
    <?php endif; ?>
    <div>
        <h1><?= e($profile['username']) ?></h1>
        <?php if ($profile['bio'] !== ''): ?><p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>
        <p class="profile-meta">Joined <?= e(format_date($profile['created_at'])) ?> · <?= (int) $listing['total'] ?> published <?= $listing['total'] === 1 ? 'post' : 'posts' ?></p>
    </div>
</section>
<?php if (!$listing['posts']): ?>
    <div class="empty-state"><h3>No published posts yet.</h3></div>
<?php else: ?>
    <section class="post-grid">
        <?php foreach ($listing['posts'] as $i => $post): render_post_card($post, ['delay' => min($i, 8) * 60]); endforeach; ?>
    </section>
<?php endif; ?>
<?php pagination_links($listing['page'], $listing['pages'], ['u' => $profile['username']]); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
