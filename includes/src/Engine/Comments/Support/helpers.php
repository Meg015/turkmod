<?php

declare(strict_types=1);

if (!function_exists('commentTopicCountsAsVisible')) {
    function commentTopicCountsAsVisible(array $comment, ?string $statusOverride = null, ?bool $deletedOverride = null): bool
    {
        $status = strtolower(trim((string) ($statusOverride ?? ($comment['status'] ?? ''))));
        $isDeleted = $deletedOverride ?? !empty($comment['deleted_at']);

        return $status === 'approved' && !$isDeleted;
    }
}

if (!function_exists('commentTopicCountDelta')) {
    function commentTopicCountDelta(array $comment, ?string $nextStatus = null, ?bool $nextDeleted = null): int
    {
        $currentVisible = commentTopicCountsAsVisible($comment);
        $nextVisible = commentTopicCountsAsVisible($comment, $nextStatus, $nextDeleted);

        return (int) $nextVisible - (int) $currentVisible;
    }
}

if (!function_exists('commentApplyTopicCountDelta')) {
    function commentApplyTopicCountDelta(?PDO $pdo, array $comment, ?string $nextStatus = null, ?bool $nextDeleted = null): int
    {
        if (!$pdo) {
            return 0;
        }

        $delta = commentTopicCountDelta($comment, $nextStatus, $nextDeleted);
        $topicId = (int) ($comment['topic_id'] ?? 0);
        if ($delta === 0 || $topicId <= 0) {
            return $delta;
        }

        if ($delta > 0) {
            $stmt = $pdo->prepare("UPDATE topics SET comment_count = comment_count + 1 WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE topics SET comment_count = GREATEST(comment_count - 1, 0) WHERE id = ?");
        }
        $stmt->execute([$topicId]);

        return $delta;
    }
}

if (!function_exists('commentSchemaHasColumn')) {
    function commentSchemaHasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?: '';
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, $cache)) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
                $cache[$cacheKey] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $cache[$cacheKey] = false;
            }
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('commentUpdateWithHistory')) {
    /** @return array{changed:bool,history_id:int,body:string,edit_reason:?string} */
    function commentUpdateWithHistory(
        PDO $pdo,
        array $comment,
        string $newBody,
        int $editorUserId,
        ?string $editReason = null,
        bool $historyEnabled = true
    ): array {
        $commentId = (int) ($comment['id'] ?? 0);
        $oldBody = (string) ($comment['body'] ?? '');
        $newBody = trim($newBody);
        $editReason = trim((string) $editReason);
        $editReason = $editReason !== '' ? mb_substr($editReason, 0, 255) : null;

        if ($commentId <= 0 || $editorUserId <= 0 || $newBody === '') {
            throw new InvalidArgumentException('Gecersiz yorum duzenleme verisi.');
        }

        if ($oldBody === $newBody) {
            return ['changed' => false, 'history_id' => 0, 'body' => $oldBody, 'edit_reason' => $editReason];
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $historyId = 0;
            if ($historyEnabled) {
                $historyStmt = $pdo->prepare(
                    'INSERT INTO comment_edit_history (comment_id, user_id, old_body, new_body, edit_reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $historyStmt->execute([$commentId, $editorUserId, $oldBody, $newBody, $editReason]);
                $historyId = (int) $pdo->lastInsertId();
            }

            if (commentSchemaHasColumn($pdo, 'comments', 'is_edited')) {
                $updateStmt = $pdo->prepare('UPDATE comments SET body = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW() WHERE id = ?');
            } else {
                $updateStmt = $pdo->prepare('UPDATE comments SET body = ?, updated_at = NOW() WHERE id = ?');
            }
            $updateStmt->execute([$newBody, $commentId]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return ['changed' => true, 'history_id' => $historyId, 'body' => $newBody, 'edit_reason' => $editReason];
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

if (!function_exists('commentSpamNormalizeComparableBody')) {
    function commentSpamNormalizeComparableBody(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = mb_strtolower($text, 'UTF-8');

        return $text;
    }
}

if (!function_exists('commentSpamNormalizeExemptionToken')) {
    function commentSpamNormalizeExemptionToken(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'i̇' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
            'â' => 'a',
            'î' => 'i',
            'û' => 'u',
        ]);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}_\-\s]+/u', '', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('commentSpamParseExemptionList')) {
    function commentSpamParseExemptionList(string $raw): array
    {
        $items = [];
        foreach (preg_split('/[\r\n,;]+/u', $raw) ?: [] as $item) {
            $item = commentSpamNormalizeExemptionToken((string) $item);
            if ($item === '') {
                continue;
            }
            $items[$item] = $item;
        }

        return array_values($items);
    }
}

if (!function_exists('commentSpamMeaningfulCharacterCount')) {
    function commentSpamMeaningfulCharacterCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (!preg_match_all('/[\p{L}\p{N}]/u', $text, $matches)) {
            return 0;
        }

        return count($matches[0] ?? []);
    }
}

if (!function_exists('commentSpamNormalizeTerm')) {
    function commentSpamNormalizeTerm(string $text): string
    {
        $text = commentSpamNormalizeComparableBody($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^[\p{Z}\p{P}\p{S}]+|[\p{Z}\p{P}\p{S}]+$/u', '', $text) ?? $text;
        $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

if (!function_exists('commentSpamParseTerms')) {
    function commentSpamParseTerms(string $raw): array
    {
        $terms = [];
        foreach (preg_split('/[\r\n,;]+/u', $raw) ?: [] as $term) {
            $normalized = commentSpamNormalizeTerm((string) $term);
            if ($normalized === '') {
                continue;
            }

            $terms[$normalized] = $normalized;
        }

        return array_values($terms);
    }
}

if (!function_exists('commentSpamFindExactTerm')) {
    function commentSpamFindExactTerm(string $body, array $terms): ?string
    {
        $normalizedBody = commentSpamNormalizeTerm($body);
        if ($normalizedBody === '' || $terms === []) {
            return null;
        }

        foreach ($terms as $term) {
            $normalizedTerm = commentSpamNormalizeTerm((string) $term);
            if ($normalizedTerm !== '' && $normalizedBody === $normalizedTerm) {
                return $normalizedTerm;
            }
        }

        return null;
    }
}

if (!function_exists('commentSpamFindContainedTerm')) {
    function commentSpamFindContainedTerm(string $body, array $terms): ?string
    {
        $normalizedBody = commentSpamNormalizeComparableBody($body);
        if ($normalizedBody === '' || $terms === []) {
            return null;
        }

        foreach ($terms as $term) {
            $normalizedTerm = commentSpamNormalizeTerm((string) $term);
            if ($normalizedTerm === '') {
                continue;
            }

            $hasWhitespace = preg_match('/[\p{Z}\s]+/u', $normalizedTerm) === 1;
            if ($hasWhitespace && str_contains($normalizedBody, $normalizedTerm)) {
                return $normalizedTerm;
            }

            if (!$hasWhitespace) {
                $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($normalizedTerm, '/') . '(?![\p{L}\p{N}_])/u';
                if (preg_match($pattern, $normalizedBody) === 1) {
                    return $normalizedTerm;
                }
            }
        }

        return null;
    }
}

if (!function_exists('commentSpamIsNonsenseSafeToken')) {
    function commentSpamIsNonsenseSafeToken(string $token): bool
    {
        $token = commentSpamNormalizeTerm($token);
        if ($token === '') {
            return true;
        }

        static $safeTokens = [
            'ai' => true,
            'api' => true,
            'apk' => true,
            'css' => true,
            'cpu' => true,
            'dns' => true,
            'dll' => true,
            'fps' => true,
            'ftp' => true,
            'gpu' => true,
            'gta' => true,
            'gtx' => true,
            'hdr' => true,
            'hdd' => true,
            'html' => true,
            'http' => true,
            'https' => true,
            'hmm' => true,
            'js' => true,
            'json' => true,
            'mod' => true,
            'pc' => true,
            'php' => true,
            'ram' => true,
            'rar' => true,
            'rdr' => true,
            'rtx' => true,
            'sql' => true,
            'ssd' => true,
            'ssh' => true,
            'tcp' => true,
            'udp' => true,
            'url' => true,
            'vpn' => true,
            'xml' => true,
            'zip' => true,
        ];

        if (isset($safeTokens[$token])) {
            return true;
        }

        return preg_match('/^(?:rtx|gtx|rx|gt|i[3579]|ps[345]|gta|rdr|cs|css|php|html|js|json|xml|api|url|vpn|dns|fps|hdr|ssd|hdd|ram|cpu|gpu|apk|rar|zip|dll|mp[34]|h26[45]|x64|x86|v)\d*$/u', $token) === 1;
    }
}

if (!function_exists('commentSpamMaxConsonantRun')) {
    function commentSpamMaxConsonantRun(string $letters): int
    {
        if ($letters === '') {
            return 0;
        }

        $letters = mb_strtolower($letters, 'UTF-8');
        $characters = preg_split('//u', $letters, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || $characters === []) {
            return 0;
        }

        $maxRun = 0;
        $currentRun = 0;
        foreach ($characters as $character) {
            if (preg_match('/[aeıioöuüâîû]/u', $character) === 1) {
                $currentRun = 0;
                continue;
            }

            $currentRun++;
            $maxRun = max($maxRun, $currentRun);
        }

        return $maxRun;
    }
}

if (!function_exists('commentSpamTokenCharacters')) {
    function commentSpamTokenCharacters(string $token): array
    {
        $characters = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? $characters : [];
    }
}

if (!function_exists('commentSpamLooksLikeRepeatedFragment')) {
    function commentSpamLooksLikeRepeatedFragment(string $token): bool
    {
        $tokenLength = mb_strlen($token, 'UTF-8');
        if ($tokenLength < 6 || $tokenLength > 40) {
            return false;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $token) ?? '';
        if (mb_strlen($letters, 'UTF-8') < 3) {
            return false;
        }

        $characters = commentSpamTokenCharacters($token);
        if ($characters === []) {
            return false;
        }

        $maxFragmentLength = min(6, intdiv($tokenLength, 2));
        for ($fragmentLength = 1; $fragmentLength <= $maxFragmentLength; $fragmentLength++) {
            $repeatCount = $tokenLength / $fragmentLength;
            if ($repeatCount < 2.75) {
                continue;
            }

            $fragmentCharacters = array_slice($characters, 0, $fragmentLength);
            $fragmentLetters = preg_replace('/[^\p{L}]+/u', '', implode('', $fragmentCharacters)) ?? '';
            if ($fragmentLetters === '') {
                continue;
            }

            $matches = 0;
            foreach ($characters as $index => $character) {
                if ($character === $fragmentCharacters[$index % $fragmentLength]) {
                    $matches++;
                }
            }

            if (($matches / $tokenLength) >= 0.82) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamLooksLikeKeyboardNoise')) {
    function commentSpamLooksLikeKeyboardNoise(string $token): bool
    {
        $asciiToken = mb_strtolower($token, 'UTF-8');
        $asciiToken = preg_replace('/[^a-z0-9]+/', '', $asciiToken) ?? '';
        $length = strlen($asciiToken);
        if ($length < 5 || $asciiToken === '' || ctype_digit($asciiToken)) {
            return false;
        }

        static $keyboardRuns = [
            'qwertyuiop',
            'poiuytrewq',
            'asdfghjkl',
            'lkjhgfdsa',
            'zxcvbnm',
            'mnbvcxz',
            'qazwsxedc',
            'cdewsxzaq',
            'plokmijn',
            'njimkolp',
        ];

        foreach ($keyboardRuns as $run) {
            if (str_contains($run, $asciiToken)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamLooksLikeLowVarietyNoise')) {
    function commentSpamLooksLikeLowVarietyNoise(string $letters): bool
    {
        $letterCount = mb_strlen($letters, 'UTF-8');
        if ($letterCount < 9) {
            return false;
        }

        $characters = commentSpamTokenCharacters($letters);
        if ($characters === []) {
            return false;
        }

        $uniqueCount = count(array_unique($characters));
        if ($uniqueCount <= 3) {
            return true;
        }

        if ($letterCount >= 14 && $uniqueCount <= 4) {
            $frequencies = array_count_values($characters);
            $highestFrequency = max($frequencies);

            return ($highestFrequency / $letterCount) >= 0.28;
        }

        return false;
    }
}

if (!function_exists('commentSpamLooksLikeLongRandomLetterRun')) {
    function commentSpamLooksLikeLongRandomLetterRun(string $letters, int $maxConsonantRun = 0): bool
    {
        $letterCount = mb_strlen($letters, 'UTF-8');
        if ($letterCount < 17) {
            return false;
        }

        $characters = commentSpamTokenCharacters($letters);
        if ($characters === []) {
            return false;
        }

        $vowelCount = preg_match_all('/[aeiou\x{0131}\x{00F6}\x{00FC}\x{00E2}\x{00EE}\x{00FB}]/u', $letters, $vowels);
        $vowelCount = $vowelCount === false ? 0 : $vowelCount;
        $vowelRatio = $vowelCount / $letterCount;
        if ($vowelRatio > 0.28) {
            return false;
        }

        $maxConsonantRun = $maxConsonantRun > 0 ? $maxConsonantRun : commentSpamMaxConsonantRun($letters);
        if ($maxConsonantRun < 5) {
            return false;
        }

        $rareConsonantCount = preg_match_all('/[jqwx]/u', $letters, $rareConsonants);
        $rareConsonantCount = $rareConsonantCount === false ? 0 : $rareConsonantCount;
        if ($maxConsonantRun >= 7 && ($rareConsonantCount >= 1 || $vowelRatio <= 0.18)) {
            return true;
        }

        $uniqueCount = count(array_unique($characters));

        return $letterCount >= 20
            && $uniqueCount >= 9
            && $rareConsonantCount >= 2
            && $maxConsonantRun >= 6;
    }
}

if (!function_exists('commentSpamLooksLikeNumericNoise')) {
    function commentSpamLooksLikeNumericNoise(string $token): bool
    {
        $token = commentSpamNormalizeTerm($token);
        if ($token === '' || preg_match('/^\p{N}+$/u', $token) !== 1) {
            return false;
        }

        if (preg_match('/^\p{N}{1,3}$/u', $token) === 1) {
            return false;
        }

        if (preg_match('/^(?:19|20)\d{2}$/u', $token) === 1) {
            return false;
        }

        return preg_match('/^\p{N}{4,20}$/u', $token) === 1;
    }
}

if (!function_exists('commentSpamLooksLikeVersionReference')) {
    function commentSpamLooksLikeVersionReference(string $body): bool
    {
        $body = commentSpamNormalizeComparableBody($body);
        if ($body === '') {
            return false;
        }

        return preg_match('/(?:^|[^\p{L}\p{N}_])v?\p{N}+(?:[._-]\p{N}+){1,4}(?:[^\p{L}\p{N}_]|$)/u', $body) === 1;
    }
}

if (!function_exists('commentSpamIsCommonShortCommentToken')) {
    function commentSpamIsCommonShortCommentToken(string $token): bool
    {
        static $tokens = [
            'as' => true,
            'az' => true,
            'bi' => true,
            'bu' => true,
            'da' => true,
            'de' => true,
            'en' => true,
            'ha' => true,
            'he' => true,
            'hi' => true,
            'ki' => true,
            'mi' => true,
            'mu' => true,
            'mü' => true,
            'mı' => true,
            'ne' => true,
            'o' => true,
            'of' => true,
            'ok' => true,
            'sa' => true,
            'su' => true,
            'şu' => true,
            've' => true,
            'ya' => true,
        ];

        return isset($tokens[commentSpamNormalizeTerm($token)]);
    }
}

if (!function_exists('commentSpamFindShortOnlyNonsenseFragments')) {
    function commentSpamFindShortOnlyNonsenseFragments(string $body): ?string
    {
        $normalizedBody = commentSpamNormalizeComparableBody($body);
        if ($normalizedBody === '' || commentSpamLooksLikeVersionReference($normalizedBody)) {
            return null;
        }

        $meaningfulCharacters = commentSpamMeaningfulCharacterCount($normalizedBody);
        if ($meaningfulCharacters < 2 || $meaningfulCharacters > 10) {
            return null;
        }

        if (preg_match_all('/[\p{L}\p{N}]+/u', $normalizedBody, $matches) < 2) {
            return null;
        }

        $tokens = [];
        foreach (($matches[0] ?? []) as $rawToken) {
            $token = commentSpamNormalizeTerm((string) $rawToken);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        $tokenCount = count($tokens);
        if ($tokenCount < 2 || $tokenCount > 5) {
            return null;
        }

        foreach ($tokens as $token) {
            if (preg_match('/^\p{L}{1,2}$/u', $token) !== 1) {
                return null;
            }

            if (commentSpamIsCommonShortCommentToken($token) || commentSpamIsNonsenseSafeToken($token)) {
                return null;
            }
        }

        return mb_substr($normalizedBody, 0, 80, 'UTF-8');
    }
}

if (!function_exists('commentSpamFindShortFragmentNoise')) {
    function commentSpamFindShortFragmentNoise(string $body): ?string
    {
        $normalizedBody = commentSpamNormalizeComparableBody($body);
        if ($normalizedBody === '' || commentSpamLooksLikeVersionReference($normalizedBody)) {
            return null;
        }

        $meaningfulCharacters = commentSpamMeaningfulCharacterCount($normalizedBody);
        if ($meaningfulCharacters < 6 || $meaningfulCharacters > 24) {
            return null;
        }

        if (preg_match_all('/[\p{L}\p{N}]+/u', $normalizedBody, $matches) < 2) {
            return null;
        }

        $tokens = [];
        foreach (($matches[0] ?? []) as $rawToken) {
            $token = commentSpamNormalizeTerm((string) $rawToken);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        $tokenCount = count($tokens);
        if ($tokenCount < 2 || $tokenCount > 5) {
            return null;
        }

        $hasSymbolNoise = preg_match('/[^\p{L}\p{N}\p{Z}\s._-]/u', $body) === 1;
        $shortFragments = [];
        $longTokens = [];

        foreach ($tokens as $token) {
            $letters = preg_replace('/[^\p{L}]+/u', '', $token) ?? '';
            if ($letters === '') {
                continue;
            }

            $tokenLength = mb_strlen($token, 'UTF-8');
            if ($tokenLength <= 2 && !commentSpamIsCommonShortCommentToken($token)) {
                $shortFragments[] = $token;
            }

            if ($tokenLength >= 7) {
                $longTokens[] = $token;
            }
        }

        if ($shortFragments === []) {
            return null;
        }

        $shortRatio = count($shortFragments) / $tokenCount;
        if (count($shortFragments) < 2 || $shortRatio < 0.5) {
            return null;
        }

        if ($longTokens === []) {
            $shortFrequencies = array_count_values($shortFragments);
            $mostRepeatedShortFragment = max($shortFrequencies);

            return $hasSymbolNoise && count($shortFragments) >= 3 && $shortRatio >= 0.6 && $mostRepeatedShortFragment >= 2
                ? mb_substr($normalizedBody, 0, 80, 'UTF-8')
                : null;
        }

        $longStartsWithShortFragment = false;
        foreach ($longTokens as $longToken) {
            foreach ($shortFragments as $shortFragment) {
                $fragmentLength = mb_strlen($shortFragment, 'UTF-8');
                if ($fragmentLength >= 2 && mb_substr($longToken, 0, $fragmentLength, 'UTF-8') === $shortFragment) {
                    $longStartsWithShortFragment = true;
                    break 2;
                }
            }
        }

        if (!$hasSymbolNoise && !$longStartsWithShortFragment) {
            return null;
        }

        return mb_substr($normalizedBody, 0, 80, 'UTF-8');
    }
}

if (!function_exists('commentSpamLooksLikeLowVarietySegment')) {
    function commentSpamLooksLikeLowVarietySegment(string $segment): bool
    {
        $segmentLength = mb_strlen($segment, 'UTF-8');
        if ($segmentLength < 7) {
            return false;
        }

        $characters = commentSpamTokenCharacters($segment);
        if ($characters === []) {
            return false;
        }

        $uniqueCount = count(array_unique($characters));
        $frequencies = array_count_values($characters);
        $highestFrequency = max($frequencies);
        $dominance = $highestFrequency / $segmentLength;

        return $uniqueCount <= 4 && $dominance >= 0.28;
    }
}

if (!function_exists('commentSpamLooksLikeShortRepeatedSegment')) {
    function commentSpamLooksLikeShortRepeatedSegment(string $segment): bool
    {
        $segmentLength = mb_strlen($segment, 'UTF-8');
        if ($segmentLength < 6 || $segmentLength > 12) {
            return false;
        }

        $characters = commentSpamTokenCharacters($segment);
        if ($characters === []) {
            return false;
        }

        $uniqueCount = count(array_unique($characters));
        if ($uniqueCount > 4) {
            return false;
        }

        for ($fragmentLength = 2; $fragmentLength <= 4; $fragmentLength++) {
            if ($segmentLength % $fragmentLength !== 0) {
                continue;
            }

            $repeatCount = intdiv($segmentLength, $fragmentLength);
            if ($repeatCount < 2) {
                continue;
            }

            $fragment = array_slice($characters, 0, $fragmentLength);
            $matches = 0;
            foreach ($characters as $index => $character) {
                if ($character === $fragment[$index % $fragmentLength]) {
                    $matches++;
                }
            }

            if ($matches === $segmentLength) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamLooksLikeLocalNoise')) {
    function commentSpamLooksLikeLocalNoise(string $letters): bool
    {
        $letterCount = mb_strlen($letters, 'UTF-8');
        if ($letterCount < 7) {
            return false;
        }

        $characters = commentSpamTokenCharacters($letters);
        if ($characters === []) {
            return false;
        }

        $maxWindowLength = min(12, $letterCount);
        for ($windowLength = 6; $windowLength <= $maxWindowLength; $windowLength++) {
            for ($offset = 0; $offset <= $letterCount - $windowLength; $offset++) {
                $segment = implode('', array_slice($characters, $offset, $windowLength));
                if (commentSpamLooksLikeShortRepeatedSegment($segment)) {
                    return true;
                }

                if (commentSpamLooksLikeLowVarietySegment($segment)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamLooksLikeNonsenseToken')) {
    function commentSpamLooksLikeNonsenseToken(string $token): bool
    {
        $token = commentSpamNormalizeTerm($token);
        if (commentSpamLooksLikeVersionReference($token)) {
            return false;
        }

        $token = preg_replace('/[^\p{L}\p{N}]+/u', '', $token) ?? '';
        if ($token === '' || commentSpamIsNonsenseSafeToken($token)) {
            return false;
        }

        if (commentSpamLooksLikeNumericNoise($token)) {
            return true;
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $token) ?? '';
        $digits = preg_replace('/[^\p{N}]+/u', '', $token) ?? '';
        $letterCount = mb_strlen($letters, 'UTF-8');
        $tokenLength = mb_strlen($token, 'UTF-8');
        if ($letterCount < 3) {
            return false;
        }

        if (commentSpamLooksLikeRepeatedFragment($token) || commentSpamLooksLikeKeyboardNoise($token)) {
            return true;
        }

        if (commentSpamLooksLikeLowVarietyNoise($letters)) {
            return true;
        }

        $hasVowel = preg_match('/[aeıioöuüâîû]/u', $letters) === 1;
        $maxConsonantRun = commentSpamMaxConsonantRun($letters);
        if (commentSpamLooksLikeLocalNoise($letters)) {
            return true;
        }

        if (commentSpamLooksLikeLongRandomLetterRun($letters, $maxConsonantRun)) {
            return true;
        }

        if ($maxConsonantRun >= 4 && $letterCount <= 8) {
            return true;
        }

        if (!$hasVowel && $letterCount <= 12) {
            return true;
        }

        if ($maxConsonantRun >= 5 && $letterCount <= 16) {
            return true;
        }

        $hasDigitBetweenLetters = preg_match('/\p{L}\p{N}+\p{L}/u', $token) === 1;
        if ($digits !== '' && $tokenLength <= 12 && ($hasDigitBetweenLetters || $maxConsonantRun >= 4)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('commentSpamFindNonsenseToken')) {
    function commentSpamFindNonsenseToken(string $body): ?string
    {
        $shortOnlyFragmentMatch = commentSpamFindShortOnlyNonsenseFragments($body);
        if ($shortOnlyFragmentMatch !== null) {
            return $shortOnlyFragmentMatch;
        }

        $fragmentNoiseMatch = commentSpamFindShortFragmentNoise($body);
        if ($fragmentNoiseMatch !== null) {
            return $fragmentNoiseMatch;
        }

        if ($body === '' || preg_match_all('/[\p{L}\p{N}]{3,}/u', $body, $matches) < 1) {
            return null;
        }

        $suspicious = [];
        $normalCount = 0;
        foreach (($matches[0] ?? []) as $rawToken) {
            $token = commentSpamNormalizeTerm((string) $rawToken);
            if ($token === '') {
                continue;
            }

            if (commentSpamLooksLikeNonsenseToken($token)) {
                $suspicious[] = $token;
            } else {
                $normalCount++;
            }
        }

        $suspiciousCount = count($suspicious);
        if ($suspiciousCount === 0) {
            return null;
        }

        if ($normalCount === 0) {
            return $suspicious[0];
        }

        if ($suspiciousCount >= 2 && $suspiciousCount >= $normalCount) {
            return $suspicious[0];
        }

        if ($suspiciousCount >= 3) {
            return $suspicious[0];
        }

        return null;
    }
}

if (!function_exists('commentSpamDefaultDuplicateWindowMinutes')) {
    function commentSpamDefaultDuplicateWindowMinutes(): int
    {
        return 5;
    }
}

if (!function_exists('commentSpamResolveDuplicateWindowMinutes')) {
    function commentSpamResolveDuplicateWindowMinutes(array $settings): int
    {
        $minutes = (int) ($settings['comment_spam_duplicate_minutes'] ?? commentSpamDefaultDuplicateWindowMinutes());

        return max(0, min(1440, $minutes));
    }
}

if (!function_exists('commentSpamDuplicateRateKey')) {
    function commentSpamDuplicateRateKey(string $body, int $topicId, ?string $ipAddress = null): string
    {
        $ipAddress = trim((string) ($ipAddress ?? (function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? ''))));
        $normalizedBody = commentSpamNormalizeComparableBody($body);

        return 'comment_spam_duplicate:' . hash('sha256', $topicId . '|' . $ipAddress . '|' . $normalizedBody);
    }
}

if (!function_exists('commentSpamFindRecentDuplicateComment')) {
    function commentSpamFindRecentDuplicateComment(PDO $pdo, string $body, int $topicId, int $userId, int $windowMinutes = 5): ?string
    {
        $topicId = max(0, $topicId);
        $userId = max(0, $userId);
        $windowMinutes = max(1, $windowMinutes);
        $normalizedBody = commentSpamNormalizeComparableBody($body);
        if ($topicId <= 0 || $userId <= 0 || $normalizedBody === '') {
            return null;
        }

        $since = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        try {
            $stmt = $pdo->prepare(
                'SELECT body
                 FROM comments
                 WHERE topic_id = ?
                   AND user_id = ?
                   AND deleted_at IS NULL
                   AND created_at >= ?
                 ORDER BY created_at DESC
                 LIMIT 50'
            );
            $stmt->execute([$topicId, $userId, $since]);

            foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $existingBody) {
                if (commentSpamNormalizeComparableBody((string) $existingBody) === $normalizedBody) {
                    return mb_substr($normalizedBody, 0, 80, 'UTF-8');
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }
}

if (!function_exists('commentSpamLooksFullyUppercase')) {
    function commentSpamLooksFullyUppercase(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $letterCount = preg_match_all('/\p{L}/u', $text, $letters) ? count($letters[0] ?? []) : 0;
        if ($letterCount < 10) {
            return false;
        }

        $lowerCount = preg_match_all('/\p{Ll}/u', $text, $lowercase) ? count($lowercase[0] ?? []) : 0;
        if ($lowerCount > 0) {
            return false;
        }

        $upperCount = preg_match_all('/\p{Lu}/u', $text, $uppercase) ? count($uppercase[0] ?? []) : 0;

        return $upperCount === $letterCount;
    }
}

if (!function_exists('commentSpamPrimaryReason')) {
    function commentSpamPrimaryReason(array $reasons): array
    {
        $priority = ['contains_term', 'exact_term', 'nonsense_word', 'duplicate_comment', 'uppercase', 'too_few_alnum'];
        foreach ($priority as $code) {
            foreach ($reasons as $reason) {
                if ((string) ($reason['code'] ?? '') === $code) {
                    return $reason;
                }
            }
        }

        return $reasons[0] ?? [];
    }
}

if (!function_exists('commentSpamUserMessage')) {
    function commentSpamUserMessage(array $reason, bool $pending = false): string
    {
        $term = trim((string) ($reason['matched_term'] ?? ''));

        if ($pending) {
            return match ((string) ($reason['code'] ?? '')) {
                'contains_term' => 'Yorumunuz cümle içinde yasaklı kelime içerdiği için yönetici onayına gönderildi: "' . $term . '".',
                'exact_term' => 'Yorumunuz tek kelime filtresine takıldığı için yönetici onayına gönderildi: "' . $term . '".',
                'nonsense_word' => 'Yorumunuz anlamsız kelime/dizi içerdiği için yönetici onayına gönderildi: "' . $term . '".',
                'duplicate_comment' => 'Aynı yorumu kısa süre içinde tekrar gönderdiğiniz için yorumunuz yönetici onayına gönderildi.',
                'uppercase' => 'Yorumunuz büyük harf kullanımından dolayı spam filtresine takıldı ve yönetici onayına gönderildi.',
                'too_few_alnum' => 'Yorumunuz yeterli harf veya rakam içermediği için yönetici onayına gönderildi.',
                default => 'Yorumunuz spam filtresine takıldı ve yönetici onayına gönderildi.',
            };
        }

        return match ((string) ($reason['code'] ?? '')) {
            'contains_term' => 'Yorumunuz cümle içinde yasaklı kelime içeriyor: "' . $term . '". Lütfen bu ifadeyi kaldırıp tekrar deneyin.',
            'exact_term' => 'Yorumunuz tek kelime filtresine takıldı: "' . $term . '". Lütfen daha açıklayıcı bir yorum yazıp tekrar deneyin.',
            'nonsense_word' => 'Yorumunuz anlamsız kelime/dizi içeriyor: "' . $term . '". Lütfen sorunu düzelterek tekrar yorum yapın.',
            'duplicate_comment' => 'Aynı yorumu kısa süre içinde tekrar gönderdiniz. Lütfen biraz bekleyip farklı bir yorum yazın.',
            'uppercase' => 'Yorumunuz büyük harf kullanımından dolayı spam filtresine takıldı. Lütfen tamamı büyük harf yazmadan tekrar deneyin.',
            'too_few_alnum' => 'Yorumunuz yeterli harf veya rakam içermiyor. Lütfen daha açıklayıcı bir yorum yazıp tekrar deneyin.',
            default => 'Yorumunuz spam filtresine takıldı. Lütfen sorunu düzelterek tekrar yorum yapın.',
        };
    }
}

if (!function_exists('commentSpamAddReason')) {
    function commentSpamAddReason(array $result, array $reason): array
    {
        $code = trim((string) ($reason['code'] ?? ''));
        if ($code === '') {
            return $result;
        }

        $reasons = array_values((array) ($result['reasons'] ?? []));
        $reasons[] = $reason;
        $primaryReason = commentSpamPrimaryReason($reasons);
        $reasonCodes = array_values(array_unique(array_map(
            static fn (array $item): string => (string) ($item['code'] ?? ''),
            $reasons
        )));
        $reasonCodes = array_values(array_filter($reasonCodes, static fn (string $itemCode): bool => $itemCode !== ''));

        $result['is_spam'] = $reasons !== [];
        $result['reasons'] = $reasons;
        $result['reason_codes'] = $reasonCodes;
        $result['primary_reason'] = $primaryReason['code'] ?? null;
        $result['matched_term'] = $primaryReason['matched_term'] ?? null;
        $result['message'] = $primaryReason !== [] ? commentSpamUserMessage($primaryReason, false) : '';

        return $result;
    }
}

if (!function_exists('commentSpamGetUserContext')) {
    function commentSpamGetUserContext(PDO $pdo, int $userId, ?string $sessionUsername = null): array
    {
        $context = [
            'username' => '',
            'group_name' => '',
            'group_slug' => '',
            'group_names' => [],
            'group_slugs' => [],
        ];

        $username = trim((string) ($sessionUsername ?? ($_SESSION['_auth_user_name'] ?? '')));
        if ($username === '' && $userId > 0 && function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username')) {
            try {
                $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $username = trim((string) ($stmt->fetchColumn() ?: ''));
            } catch (Throwable $e) {
                $username = '';
            }
        }
        $context['username'] = $username;

        $groupRows = [];
        if ($userId > 0 && function_exists('usersGroupsAvailable') && usersGroupsAvailable($pdo)) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT g.name, g.slug
                     FROM user_group_members m
                     INNER JOIN user_groups g ON g.id = m.group_id
                     WHERE m.user_id = ? AND g.is_active = 1
                     ORDER BY m.is_primary DESC, g.display_order ASC, g.name ASC'
                );
                $stmt->execute([$userId]);
                $groupRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $groupRows = [];
            }
        } elseif ($userId > 0 && function_exists('usersPrimaryGroupForUser')) {
            $group = usersPrimaryGroupForUser($pdo, $userId);
            if (is_array($group) && $group !== []) {
                $groupRows = [$group];
            }
        }

        foreach ($groupRows as $groupRow) {
            $groupName = trim((string) ($groupRow['name'] ?? $groupRow['group_name'] ?? ''));
            $groupSlug = trim((string) ($groupRow['slug'] ?? $groupRow['group_slug'] ?? ''));
            if ($groupName !== '' && !in_array($groupName, $context['group_names'], true)) {
                $context['group_names'][] = $groupName;
            }
            if ($groupSlug !== '' && !in_array($groupSlug, $context['group_slugs'], true)) {
                $context['group_slugs'][] = $groupSlug;
            }
        }

        $context['group_name'] = (string) ($context['group_names'][0] ?? '');
        $context['group_slug'] = (string) ($context['group_slugs'][0] ?? '');

        return $context;
    }
}

if (!function_exists('commentSpamIsUserExempt')) {
    function commentSpamIsUserExempt(PDO $pdo, array $settings, int $userId = 0, ?array $context = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $context ??= commentSpamGetUserContext($pdo, $userId);

        $usernameTokens = commentSpamParseExemptionList((string) ($settings['comment_spam_exempt_usernames'] ?? ''));
        if ($usernameTokens !== []) {
            $username = commentSpamNormalizeExemptionToken((string) ($context['username'] ?? ''));
            if ($username !== '' && in_array($username, $usernameTokens, true)) {
                return true;
            }
        }

        $groupTokens = commentSpamParseExemptionList((string) ($settings['comment_spam_exempt_groups'] ?? ''));
        if ($groupTokens !== []) {
            foreach ((array) ($context['group_names'] ?? []) as $groupName) {
                $candidate = commentSpamNormalizeExemptionToken((string) $groupName);
                if ($candidate !== '' && in_array($candidate, $groupTokens, true)) {
                    return true;
                }
            }

            foreach ((array) ($context['group_slugs'] ?? []) as $groupSlug) {
                $candidate = commentSpamNormalizeExemptionToken((string) $groupSlug);
                if ($candidate !== '' && in_array($candidate, $groupTokens, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamEvaluate')) {
    function commentSpamEvaluate(
        string $body,
        array $settings
    ): array {
        $reasons = [];
        $normalizedBody = commentSpamNormalizeComparableBody($body);
        if ((string) ($settings['comment_spam_detection'] ?? '1') !== '1') {
            return [
                'is_spam' => false,
                'reasons' => [],
                'reason_codes' => [],
                'primary_reason' => null,
                'matched_term' => null,
                'message' => '',
                'normalized_body' => $normalizedBody,
                'meaningful_chars' => commentSpamMeaningfulCharacterCount($body),
            ];
        }

        $meaningfulChars = commentSpamMeaningfulCharacterCount($body);

        $containsTerms = commentSpamParseTerms((string) ($settings['comment_spam_contains_terms'] ?? ''));
        $containsMatch = commentSpamFindContainedTerm($body, $containsTerms);
        if ($containsMatch !== null) {
            $reasons[] = [
                'code' => 'contains_term',
                'matched_term' => $containsMatch,
            ];
        }

        $exactTerms = commentSpamParseTerms((string) ($settings['comment_spam_exact_terms'] ?? ''));
        $exactMatch = commentSpamFindExactTerm($body, $exactTerms);
        if ($exactMatch !== null) {
            $reasons[] = [
                'code' => 'exact_term',
                'matched_term' => $exactMatch,
            ];
        }

        if ((string) ($settings['comment_spam_nonsense_words_enabled'] ?? '1') === '1') {
            $nonsenseMatch = commentSpamFindNonsenseToken($body);
            if ($nonsenseMatch !== null) {
                $reasons[] = [
                    'code' => 'nonsense_word',
                    'matched_term' => $nonsenseMatch,
                ];
            }
        }

        $minAlnumCount = max(0, (int) ($settings['comment_spam_min_alnum_count'] ?? 2));
        if ($minAlnumCount > 0 && $meaningfulChars < $minAlnumCount) {
            $reasons[] = [
                'code' => 'too_few_alnum',
                'minimum' => $minAlnumCount,
                'actual' => $meaningfulChars,
            ];
        }

        if ((string) ($settings['comment_spam_block_uppercase'] ?? '0') === '1'
            && commentSpamLooksFullyUppercase($body)
        ) {
            $reasons[] = [
                'code' => 'uppercase',
            ];
        }

        $primaryReason = commentSpamPrimaryReason($reasons);
        $reasonCodes = array_values(array_unique(array_map(
            static fn (array $reason): string => (string) ($reason['code'] ?? ''),
            $reasons
        )));
        $reasonCodes = array_values(array_filter($reasonCodes, static fn (string $code): bool => $code !== ''));

        return [
            'is_spam' => $reasons !== [],
            'reasons' => array_values($reasons),
            'reason_codes' => $reasonCodes,
            'primary_reason' => $primaryReason['code'] ?? null,
            'matched_term' => $primaryReason['matched_term'] ?? null,
            'message' => $primaryReason !== [] ? commentSpamUserMessage($primaryReason, false) : '',
            'normalized_body' => $normalizedBody,
            'meaningful_chars' => $meaningfulChars,
        ];
    }
}
