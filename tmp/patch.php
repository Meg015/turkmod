<?php
$file = 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\src\\Engine\\Topics\\Http\\topic-page-content.php';
$content = file_get_contents($file);

// Re-add helpers.php require
$content = preg_replace(
    '/(require_once \$projectRoot \. "\/includes\/src\/Engine\/Seo\/Legacy\/legacy-redirect-helpers\.php";)/',
    "$1\nrequire_once \$projectRoot . \"/includes/src/Engine/Seo/Legacy/helpers.php\";",
    $content
);

// Replace truncation logic
$search = <<<'EOD'
$metaDescriptionSource = trim((string) ($topic['meta_description'] ?? ''));
if ($metaDescriptionSource === '') {
    $metaDescriptionSource = $cleanTopicDescription;
}
$metaDescription = mb_substr(
    strip_tags($metaDescriptionSource),
    0,
    META_DESCRIPTION_MAX_LENGTH,
    'UTF-8'
);
EOD;

// Fix possible line endings
$searchRegex = preg_quote(str_replace("\r", "", $search), '/');
$searchRegex = str_replace("\n", "\r?\n", $searchRegex);

$replace = <<<'EOD'
$metaDescriptionSource = trim((string) ($topic['meta_description'] ?? ''));
if ($metaDescriptionSource === '') {
    $metaDescriptionSource = $cleanTopicDescription;
}
$metaDescriptionRaw = trim(preg_replace('/\s+/u', ' ', strip_tags($metaDescriptionSource)) ?? '');
if (mb_strlen($metaDescriptionRaw, 'UTF-8') > META_DESCRIPTION_MAX_LENGTH) {
    $truncated = mb_substr($metaDescriptionRaw, 0, META_DESCRIPTION_MAX_LENGTH - 3, 'UTF-8');
    $lastSpacePos = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    if ($lastSpacePos !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpacePos, 'UTF-8');
    }
    $metaDescription = rtrim($truncated, '.,!?;: ') . '...';
} else {
    $metaDescription = $metaDescriptionRaw;
}
EOD;

$newContent = preg_replace('/' . $searchRegex . '/', str_replace("\n", "\r\n", $replace), $content);

file_put_contents($file, $newContent);
echo "Replaced successfully\n";
