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

/** Turn a title into a URL-friendly, unique slug. */
function make_slug(PDO $pdo, string $title, int $ignoreId = 0): string
{
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    if ($base === '') {
        $base = 'post';
    }
    $slug = $base;
    $i = 1;

    $stmt = $pdo->prepare('SELECT id FROM blogPost WHERE slug = ? AND id != ?');
    while (true) {
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetch()) {
            break;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
    return $slug;
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
