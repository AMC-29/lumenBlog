<?php
/** Escape a string for safe HTML output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Render the shared Lumen brand logo. */
function brand_logo(string $class = 'brand-logo'): string
{
    return '<img class="' . e($class) . '" src="images/logo-Photoroom.png" alt="Lumen logo">';
}

function post_url(string $slug): string
{
    return 'view.php?slug=' . rawurlencode($slug);
}

function tag_url(string $slug): string
{
    return 'tag.php?slug=' . rawurlencode($slug);
}

function profile_url(string $username): string
{
    return 'profile.php?u=' . rawurlencode($username);
}

function valid_image_filename(?string $filename): bool
{
    return $filename !== null && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename) === 1;
}

function image_url(string $kind, ?string $filename): ?string
{
    if (!in_array($kind, ['covers', 'avatars'], true) || !valid_image_filename($filename)) {
        return null;
    }
    return 'uploads/' . $kind . '/' . $filename;
}

/** Unicode-aware slug base shared by tags and post slugs. */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text);
    return trim(is_string($slug) ? $slug : '', '-');
}

/** Turn a title into a URL-friendly, unique slug. */
function make_slug(PDO $pdo, string $title, int $ignoreId = 0): string
{
    $base = mb_substr(slugify($title), 0, 200, 'UTF-8');
    if ($base === '') {
        $base = 'post';
    }

    $slug = $base;
    $i = 1;
    $current = $pdo->prepare('SELECT id FROM blogPost WHERE slug = ? AND id != ?');
    $history = $pdo->prepare('SELECT post_id FROM slug_history WHERE slug = ? AND post_id != ?');
    while (true) {
        $current->execute([$slug, $ignoreId]);
        $history->execute([$slug, $ignoreId]);
        if (!$current->fetch() && !$history->fetch()) {
            return $slug;
        }
        $i++;
        $suffix = '-' . $i;
        $slug = mb_substr($base, 0, 200 - strlen($suffix), 'UTF-8') . $suffix;
    }
}

/** Read a safe positive page number from a query parameter. */
function requested_page($value): int
{
    if (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value)) {
        return 1;
    }
    $normalized = ltrim($value, '0');
    if ($normalized === '') {
        return 1;
    }
    $page = filter_var($normalized, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? PHP_INT_MAX : (int) $page;
}

/**
 * Fetch a paginated post list. SQL fragments are fixed by callers; all data
 * values are prepared, and LIMIT/OFFSET are emitted as integers for native PDO.
 */
function paginated_posts(
    PDO $pdo,
    string $fromSql,
    string $whereSql,
    array $params,
    int $requestedPage,
    int $perPage = 9
): array {
    $count = $pdo->prepare('SELECT COUNT(DISTINCT p.id) FROM ' . $fromSql . ' WHERE ' . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $requestedPage), $pages);
    $offset = (int) (($page - 1) * $perPage);
    $limit = (int) $perPage;

    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.*, u.username, u.avatar AS author_avatar FROM ' . $fromSql .
        ' WHERE ' . $whereSql .
        ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    attach_post_tags($pdo, $posts);

    return ['posts' => $posts, 'page' => $page, 'pages' => $pages, 'total' => $total];
}

/** Attach tags in one query, avoiding per-card queries. */
function attach_post_tags(PDO $pdo, array &$posts): void
{
    if (!$posts) {
        return;
    }
    $ids = array_map(static fn(array $post): int => (int) $post['id'], $posts);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT pt.post_id, t.name, t.slug FROM post_tag pt JOIN tag t ON t.id = pt.tag_id ' .
        'WHERE pt.post_id IN (' . $marks . ') ORDER BY t.name'
    );
    $stmt->execute($ids);
    $byPost = [];
    foreach ($stmt->fetchAll() as $tag) {
        $byPost[(int) $tag['post_id']][] = ['name' => $tag['name'], 'slug' => $tag['slug']];
    }
    foreach ($posts as &$post) {
        $post['tags'] = $byPost[(int) $post['id']] ?? [];
    }
    unset($post);
}

/** Render a page-number control while preserving non-page query parameters. */
function pagination_links(int $page, int $pages, array $params = []): void
{
    if ($pages <= 1) {
        return;
    }
    $url = static function (int $number) use ($params): string {
        $query = http_build_query(array_merge($params, ['page' => $number]));
        return '?' . $query;
    };
    $numbers = [1];
    for ($number = max(2, $page - 1); $number <= min($pages - 1, $page + 1); $number++) {
        $numbers[] = $number;
    }
    if ($pages > 1) {
        $numbers[] = $pages;
    }
    $numbers = array_values(array_unique($numbers));
    ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?><a href="<?= e($url($page - 1)) ?>" rel="prev">Prev</a><?php endif; ?>
        <?php $previous = 0; foreach ($numbers as $number): ?>
            <?php if ($number - $previous > 1): ?><span class="pagination-ellipsis" aria-hidden="true">…</span><?php endif; ?>
            <?php if ($number === $page): ?>
                <span class="current" aria-current="page"><?= $number ?></span>
            <?php else: ?>
                <a href="<?= e($url($number)) ?>"><?= $number ?></a>
            <?php endif; ?>
            <?php $previous = $number; ?>
        <?php endforeach; ?>
        <?php if ($page < $pages): ?><a href="<?= e($url($page + 1)) ?>" rel="next">Next</a><?php endif; ?>
    </nav>
    <?php
}

/** Return the most-used public tags. */
function popular_tags(PDO $pdo, int $limit = 8): array
{
    $limit = (int) max(1, $limit);
    $stmt = $pdo->prepare(
        'SELECT t.name, t.slug, COUNT(*) AS uses FROM tag t
         JOIN post_tag pt ON pt.tag_id = t.id
         JOIN blogPost p ON p.id = pt.post_id
         WHERE p.status = \'published\'
         GROUP BY t.id, t.name, t.slug
         ORDER BY uses DESC, t.name ASC LIMIT ' . $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function parse_post_tags(string $input): array
{
    $tags = [];
    $seen = [];
    foreach (explode(',', $input) as $rawTag) {
        $name = trim($rawTag);
        if ($name === '') {
            continue;
        }
        if (mb_strlen($name, 'UTF-8') > 30) {
            throw new InvalidArgumentException('Each tag must be 30 characters or fewer.');
        }
        $slug = slugify($name);
        if ($slug === '') {
            throw new InvalidArgumentException('Tags must contain at least one letter or number.');
        }
        if (isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;
        $tags[] = ['name' => $name, 'slug' => $slug];
    }
    if (count($tags) > 5) {
        throw new InvalidArgumentException('Use no more than 5 tags.');
    }
    return $tags;
}

function replace_post_tags(PDO $pdo, int $postId, array $tags): void
{
    $delete = $pdo->prepare('DELETE FROM post_tag WHERE post_id = ?');
    $delete->execute([$postId]);
    $insert = $pdo->prepare('INSERT INTO tag (name, slug) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $attach = $pdo->prepare('INSERT INTO post_tag (post_id, tag_id) VALUES (?, ?)');
    foreach ($tags as $tag) {
        $insert->execute([$tag['name'], $tag['slug']]);
        $tagId = $pdo->lastInsertId();
        $attach->execute([$postId, (int) $tagId]);
    }
}

/** Render a card with tag and author links outside its post link. */
function render_post_card(array $post, array $opts = []): void
{
    $marquee = !empty($opts['marquee']);
    $clone = !empty($opts['clone']);
    $delay = (int) ($opts['delay'] ?? 0);
    $coverUrl = image_url('covers', $post['cover_image'] ?? null);
    ?>
    <article class="post-card<?= $marquee ? '' : ' reveal' ?>"<?= $marquee ? '' : ' style="--delay: ' . $delay . 'ms"' ?>>
        <a href="<?= e(post_url($post['slug'])) ?>" class="post-card-link"<?= $clone ? ' tabindex="-1"' : '' ?>>
            <div class="post-card-top"></div>
            <?php if ($coverUrl): ?><img class="post-card-cover" src="<?= e($coverUrl) ?>" alt="" loading="lazy"><?php endif; ?>
            <div class="post-card-body">
                <?php if (($post['status'] ?? '') === 'draft'): ?><span class="status-badge">Draft</span><?php endif; ?>
                <h2><?= e($post['title']) ?></h2>
                <p class="post-excerpt"><?= e(excerpt($post['content'])) ?></p>
            </div>
        </a>
        <?php if (!empty($post['tags'])): ?>
            <div class="card-tags">
                <?php foreach ($post['tags'] as $tag): ?><a class="tag-chip" href="<?= e(tag_url($tag['slug'])) ?>"><?= e($tag['name']) ?></a><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="post-card-meta">
            <?php $avatarUrl = image_url('avatars', $post['author_avatar'] ?? null); ?>
            <?php if ($avatarUrl): ?><img class="avatar-image small" src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><span class="avatar-badge small"><?= e(initials($post['username'])) ?></span><?php endif; ?>
            <a class="post-card-author" href="<?= e(profile_url($post['username'])) ?>"><?= e($post['username']) ?></a>
            <span class="meta-dot">•</span>
            <span><?= e(format_date($post['published_at'] ?? $post['created_at'])) ?></span>
            <span class="meta-dot">•</span>
            <span><?= e(reading_time($post['content'])) ?></span>
        </div>
    </article>
    <?php
}

/** Rough reading time estimate, ~200 words per minute. */
function reading_time(string $markdown): string
{
    $words = str_word_count(strip_tags($markdown));
    $minutes = max(1, (int) ceil($words / 200));
    return $minutes . ' min read';
}

/** First couple of sentences of a post, for card previews. */
function excerpt(string $markdown, int $length = 160): string
{
    $plain = preg_replace('/[#>*`_\[\]!-]/', '', $markdown);
    $plain = trim(preg_replace('/\s+/', ' ', $plain));
    if (mb_strlen($plain) <= $length) {
        return $plain;
    }
    return mb_substr($plain, 0, $length) . '…';
}

/** Friendly relative-ish date, e.g. "Aug 6, 2026". */
function format_date(string $datetime): string
{
    return date('M j, Y', strtotime($datetime));
}

/** Single uppercase initial for the little avatar badge. */
function initials(string $name): string
{
    return mb_strtoupper(mb_substr($name, 0, 1));
}
