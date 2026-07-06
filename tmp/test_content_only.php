<?php
require 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\init.php';

$stmt = $pdo->prepare('SELECT * FROM topics WHERE id = ?');
$stmt->execute([159]);
$topic = $stmt->fetch();

$descHtml = (string) ($topic['topic_descriptions'] ?? '');
if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
    $descHtml = topicDescriptionWithoutRepeatedTitle($descHtml, (string) ($topic['title'] ?? ''));
}
$descHtml = html_entity_decode($descHtml, ENT_QUOTES, 'UTF-8');
$description = trim(preg_replace('/\s+/u', ' ', strip_tags($descHtml)) ?? '');

var_dump($description);
