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

if (!function_exists('commentSpamCountLinks')) {
    function commentSpamCountLinks(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (!preg_match_all('~https?://[^\s<>()]+~iu', $text, $matches)) {
            return 0;
        }

        return count($matches[0] ?? []);
    }
}

if (!function_exists('commentSpamHasRepeatedCharacters')) {
    function commentSpamHasRepeatedCharacters(string $text, int $limit): bool
    {
        $limit = max(0, $limit);
        if ($limit <= 0 || $text === '') {
            return false;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || $characters === []) {
            return false;
        }

        $lastCharacter = null;
        $runLength = 0;
        foreach ($characters as $character) {
            if ($character === $lastCharacter) {
                $runLength++;
            } else {
                $lastCharacter = $character;
                $runLength = 1;
            }

            if ($runLength > $limit) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('commentSpamNormalizeMeaninglessPhrase')) {
    function commentSpamNormalizeMeaninglessPhrase(string $text): string
    {
        $text = commentSpamNormalizeComparableBody($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/^[\p{Z}\p{P}\p{S}]+|[\p{Z}\p{P}\p{S}]+$/u', '', $text) ?? $text;
        $text = trim($text);

        return $text;
    }
}

if (!function_exists('commentSpamParseMeaninglessPhrases')) {
    function commentSpamParseMeaninglessPhrases(string $raw): array
    {
        $phrases = [];
        foreach (preg_split('/[\r\n]+/u', $raw) ?: [] as $phrase) {
            $phrase = commentSpamNormalizeMeaninglessPhrase((string) $phrase);
            if ($phrase === '') {
                continue;
            }

            $phrases[$phrase] = $phrase;
        }

        return array_values($phrases);
    }
}

if (!function_exists('commentSpamMeaninglessPhraseMatch')) {
    function commentSpamMeaninglessPhraseMatch(string $body, array $phrases): ?string
    {
        $normalizedBody = commentSpamNormalizeMeaninglessPhrase($body);
        if ($normalizedBody === '' || $phrases === []) {
            return null;
        }

        foreach ($phrases as $phrase) {
            $needle = commentSpamNormalizeMeaninglessPhrase((string) $phrase);
            if ($needle !== '' && $normalizedBody === $needle) {
                return $needle;
            }
        }

        return null;
    }
}

if (!function_exists('commentSpamCapsStats')) {
    function commentSpamCapsStats(string $text): array
    {
        $letterCount = 0;
        $upperCount = 0;

        if ($text !== '') {
            if (preg_match_all('/\p{L}/u', $text, $letters)) {
                $letterCount = count($letters[0] ?? []);
            }
            if (preg_match_all('/\p{Lu}/u', $text, $uppercase)) {
                $upperCount = count($uppercase[0] ?? []);
            }
        }

        $ratio = $letterCount > 0 ? $upperCount / $letterCount : 0.0;

        return [
            'letters' => $letterCount,
            'upper' => $upperCount,
            'ratio' => $ratio,
        ];
    }
}

if (!function_exists('commentSpamGuestDuplicateKey')) {
    function commentSpamGuestDuplicateKey(string $body, ?string $ipAddress = null): string
    {
        $ipAddress = trim((string) ($ipAddress ?? (function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? ''))));
        $normalizedBody = commentSpamNormalizeComparableBody($body);

        return 'comment_spam_guest_duplicate:' . hash('sha256', $ipAddress . '|' . $normalizedBody);
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

if (!function_exists('commentSpamHasRecentDuplicateComment')) {
    function commentSpamHasRecentDuplicateComment(PDO $pdo, int $userId, string $normalizedBody, int $windowMinutes): bool
    {
        if ($userId <= 0 || $windowMinutes <= 0 || $normalizedBody === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT body
                 FROM comments
                 WHERE user_id = ?
                   AND deleted_at IS NULL
                   AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 ORDER BY created_at DESC
                 LIMIT 50'
            );
            $stmt->execute([$userId, $windowMinutes]);

            foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $body) {
                if (commentSpamNormalizeComparableBody((string) $body) === $normalizedBody) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}

if (!function_exists('commentSpamEvaluate')) {
    function commentSpamEvaluate(
        string $body,
        array $settings,
        ?PDO $pdo = null,
        int $userId = 0,
        ?string $guestDuplicateKey = null
    ): array {
        $reasons = [];
        if ((string) ($settings['comment_spam_detection'] ?? '1') !== '1') {
            return [
                'is_spam' => false,
                'reasons' => [],
                'normalized_body' => commentSpamNormalizeComparableBody($body),
            ];
        }

        $normalizedBody = commentSpamNormalizeComparableBody($body);
        $meaningfulChars = commentSpamMeaningfulCharacterCount($body);

        $minMeaningfulChars = max(0, (int) ($settings['comment_spam_min_meaningful_chars'] ?? 2));
        if ($minMeaningfulChars > 0 && $meaningfulChars < $minMeaningfulChars) {
            $reasons[] = 'too_few_meaningful_chars';
        }

        if ((string) ($settings['comment_spam_punctuation_only_enabled'] ?? '1') === '1'
            && $body !== ''
            && $meaningfulChars === 0
        ) {
            $reasons[] = 'punctuation_only';
        }

        if ((string) ($settings['comment_spam_meaningless_enabled'] ?? '1') === '1') {
            $phrases = commentSpamParseMeaninglessPhrases((string) ($settings['comment_spam_meaningless_phrases'] ?? ''));
            if ($phrases !== [] && commentSpamMeaninglessPhraseMatch($body, $phrases) !== null) {
                $reasons[] = 'meaningless_phrase';
            }
        }

        if ((string) ($settings['comment_spam_repeated_chars_enabled'] ?? '1') === '1') {
            $repeatLimit = (int) ($settings['comment_spam_repeated_chars_limit'] ?? 5);
            if (commentSpamHasRepeatedCharacters($body, $repeatLimit)) {
                $reasons[] = 'repeated_chars';
            }
        }

        if ((string) ($settings['comment_spam_caps_enabled'] ?? '1') === '1') {
            $capsStats = commentSpamCapsStats($body);
            $capsMinLetters = max(0, (int) ($settings['comment_spam_caps_min_letters'] ?? 10));
            $capsPercent = max(1, min(100, (int) ($settings['comment_spam_caps_percent'] ?? 70)));
            if ((int) ($capsStats['letters'] ?? 0) >= $capsMinLetters
                && (float) ($capsStats['ratio'] ?? 0.0) > ($capsPercent / 100)
            ) {
                $reasons[] = 'caps';
            }
        }

        $maxLinks = (int) ($settings['comment_spam_max_links'] ?? 3);
        if ($maxLinks >= 0 && commentSpamCountLinks($body) > $maxLinks) {
            $reasons[] = 'too_many_links';
        }

        $duplicateWindowMinutes = max(0, (int) ($settings['comment_spam_duplicate_window_minutes'] ?? 5));
        if ($duplicateWindowMinutes > 0) {
            if ($userId > 0 && $pdo instanceof PDO) {
                if (commentSpamHasRecentDuplicateComment($pdo, $userId, $normalizedBody, $duplicateWindowMinutes)) {
                    $reasons[] = 'duplicate_comment';
                }
            } else {
                $guestDuplicateKey = $guestDuplicateKey ?? commentSpamGuestDuplicateKey($body);
                if ($guestDuplicateKey !== '' && function_exists('checkRateLimit') && !checkRateLimit($guestDuplicateKey, 1, $duplicateWindowMinutes)) {
                    $reasons[] = 'duplicate_comment';
                }
            }
        }

        return [
            'is_spam' => $reasons !== [],
            'reasons' => array_values(array_unique($reasons)),
            'normalized_body' => $normalizedBody,
        ];
    }
}
