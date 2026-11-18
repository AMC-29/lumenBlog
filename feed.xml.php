<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/rss+xml; charset=UTF-8');
$username = is_string($_GET['author'] ?? null) ? trim($_GET['author']) : '';
$where = 'p.status = ?';
$params = ['published'];
if ($username !== '') {
    $where .= ' AND u.username = ?';
    $params[] = $username;
}
$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.slug, p.content, p.published_at, u.username
     FROM blogPost p JOIN user u ON u.id = p.user_id
     WHERE ' . $where . ' ORDER BY p.published_at DESC, p.id DESC LIMIT 20'
);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$xml = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
    <title><?= $xml($username === '' ? 'Lumen' : $username . ' on Lumen') ?></title>
    <link><?= $xml(rtrim(SITE_URL, '/') . '/') ?></link>
    <description><?= $xml('Published stories from Lumen.') ?></description>
    <?php foreach ($posts as $post):
        $permalink = rtrim(SITE_URL, '/') . '/' . post_url($post['slug']);
        $tagStmt = $pdo->prepare('SELECT t.name FROM tag t JOIN post_tag pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
        $tagStmt->execute([(int) $post['id']]);
        $tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
        $pubDate = date(DATE_RSS, strtotime($post['published_at'] ?? 'now'));
    ?>
    <item>
        <title><?= $xml($post['title']) ?></title>
        <link><?= $xml($permalink) ?></link>
        <guid isPermaLink="true"><?= $xml($permalink) ?></guid>
        <pubDate><?= $xml($pubDate) ?></pubDate>
        <author><?= $xml($post['username']) ?></author>
        <?php foreach ($tags as $tag): ?><category><?= $xml($tag) ?></category><?php endforeach; ?>
        <description><?= $xml(excerpt($post['content'])) ?></description>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
