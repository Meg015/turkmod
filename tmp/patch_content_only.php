<?php
function patchFile($file) {
    $content = file_get_contents($file);

    $search = <<<'EOD'
        $descriptionSource = trim((string) ($topic['meta_description'] ?? ''));
        if ($descriptionSource === '') {
            $descHtml = (string) ($topic['topic_descriptions'] ?? ($topic['description'] ?? ''));
            if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
                $descHtml = topicDescriptionWithoutRepeatedTitle($descHtml, (string) ($topic['title'] ?? ''));
            }
            $descriptionSource = $descHtml;
        }
EOD;

    $replace = <<<'EOD'
        // Always generate description directly from content, ignoring the potentially outdated meta_description field
        $descHtml = (string) ($topic['topic_descriptions'] ?? ($topic['description'] ?? ''));
        if (function_exists('topicDescriptionWithoutRepeatedTitle')) {
            $descHtml = topicDescriptionWithoutRepeatedTitle($descHtml, (string) ($topic['title'] ?? ''));
        }
        $descriptionSource = $descHtml;
EOD;

    $search = str_replace("\r\n", "\n", $search);
    $replace = str_replace("\r\n", "\n", $replace);

    $content = str_replace("\r\n", "\n", $content);
    $newContent = str_replace($search, $replace, $content);

    if ($newContent !== $content && $newContent !== null) {
        file_put_contents($file, $newContent);
        echo "Patched $file\n";
    } else {
        echo "Failed to patch $file\n";
    }
}

patchFile('c:\\xampp\\htdocs\\yenidosyalar\\includes\\src\\Engine\\Seo\\Legacy\\meta-tags.php');
