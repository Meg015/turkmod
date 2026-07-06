<?php
function patchFile($file) {
    $content = file_get_contents($file);

    $search = <<<'EOD'
        $maxLength = (int) ($settings['meta_description_max_length'] ?? ($settings['meta_description_length'] ?? 160));
        if ($description !== '' && mb_strlen($description, 'UTF-8') > $maxLength) {
            $truncated = mb_substr($description, 0, $maxLength - 3, 'UTF-8');
            if (preg_match('/.*[.!?]/us', $truncated, $matches) && mb_strlen($matches[0], 'UTF-8') > 50) {
                $description = rtrim($matches[0]);
            } else {
                $lastSpacePos = mb_strrpos($truncated, ' ', 0, 'UTF-8');
                if ($lastSpacePos !== false) {
                    $truncated = mb_substr($truncated, 0, $lastSpacePos, 'UTF-8');
                }
                $truncated = preg_replace('/\s*[\(\[][^\)\]]*$/u', '', $truncated);
                $description = rtrim($truncated, '.,!?;: -') . '...';
            }
        }
EOD;

    $replace = <<<'EOD'
        $maxLength = (int) ($settings['meta_description_max_length'] ?? ($settings['meta_description_length'] ?? 160));
        if ($description !== '' && mb_strlen($description, 'UTF-8') > $maxLength) {
            $truncated = mb_substr($description, 0, $maxLength - 3, 'UTF-8');
            if (preg_match('/.*[.!?]/us', $truncated, $matches) && mb_strlen($matches[0], 'UTF-8') > 50) {
                $description = rtrim($matches[0], '.,!?;: ') . '...';
            } else {
                $lastSpacePos = mb_strrpos($truncated, ' ', 0, 'UTF-8');
                if ($lastSpacePos !== false) {
                    $truncated = mb_substr($truncated, 0, $lastSpacePos, 'UTF-8');
                }
                $truncated = preg_replace('/\s*[\(\[][^\)\]]*$/u', '', $truncated);
                $description = rtrim($truncated, '.,!?;: -') . '...';
            }
        }
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
