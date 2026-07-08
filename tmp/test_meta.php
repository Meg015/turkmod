<?php
require 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\init.php';
$stmt = $pdo->prepare('SELECT t.*, cat.name as category, u.username AS author FROM topics t LEFT JOIN categories cat ON t.category_id = cat.id LEFT JOIN users u ON t.author_id = u.id WHERE t.id = ?');
$stmt->execute([159]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    die("Topic not found\n");
}

$settings = function_exists('seoSettings') ? seoSettings() : (function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : []);

require_once 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\src\\Engine\\Seo\\Legacy\\meta-tags.php';

echo "=== RAW TOPIC ===\n";
var_dump($topic['meta_description'], $topic['topic_descriptions']);

echo "\n=== SEO META TAGS ===\n";
$tags = seoGenerateTopicMeta($topic, $settings, '', true);
echo $tags;
