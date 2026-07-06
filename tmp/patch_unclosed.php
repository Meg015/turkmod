<?php
$file = 'c:\\xampp\\htdocs\\yenidosyalar\\includes\\src\\Engine\\Topics\\Http\\topic-page-content.php';
$content = file_get_contents($file);

$search = '$metaDescription = mb_substr(
    strip_tags($metaDescriptionSource),
    0,
    META_DESCRIPTION_MAX_LENGTH,
    \'UTF-8\'
);';

$replace = '$metaDescriptionRaw = trim(preg_replace(\'/\s+/u\', \' \', strip_tags($metaDescriptionSource)) ?? \'\');
if (mb_strlen($metaDescriptionRaw, \'UTF-8\') > META_DESCRIPTION_MAX_LENGTH) {
    $truncated = mb_substr($metaDescriptionRaw, 0, META_DESCRIPTION_MAX_LENGTH - 3, \'UTF-8\');
    $lastSpacePos = mb_strrpos($truncated, \' \', 0, \'UTF-8\');
    if ($lastSpacePos !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpacePos, \'UTF-8\');
    }
    $truncated = preg_replace(\'/\s*[\(\[][^\)\]]*$/u\', \'\', $truncated);
    $metaDescription = rtrim($truncated, \'.,!?;: -\') . \'...\';
} else {
    $metaDescription = $metaDescriptionRaw;
}';

$content = str_replace(str_replace("\n", "\r\n", $search), str_replace("\n", "\r\n", $replace), $content);
$content = str_replace(str_replace("\r\n", "\n", $search), str_replace("\r\n", "\n", $replace), $content);

file_put_contents($file, $content);
echo "Done\n";
