<?php

declare(strict_types=1);

if (!function_exists('eventsAllowedActivityTypes')) {
    function eventsAllowedActivityTypes(): array
    {
        return [
            'comment_created' => 'Yorum yaptı',
            'topic_created' => 'Konu açtı',
            'comment_reaction_added' => 'Yoruma emoji attı',
            'topic_favorite_added' => 'Konuyu favoriye ekledi',
            'wheel_spin' => 'Çark çevirdi',
            'daily_login' => 'Günlük giriş yaptı',
            'topic_downloaded' => 'Dosya/mod indirdi',
            'user_registered' => 'Kayit oldu',
            'profile_avatar_uploaded' => 'Avatar yukledi',
            'topic_rated' => 'Konuya puan verdi',
            'collection_created' => 'Koleksiyon olusturdu',
            'topic_viewed' => 'Konu goruntuledi',
            'topic_updated' => 'Konu guncelledi',
            'topic_reported' => 'Konu raporladi',
            'user_reported' => 'Kullanici raporladi',
            'comment_mention_added' => 'Yorumda kullanici etiketledi',
            'collection_item_added' => 'Koleksiyona konu ekledi',
            'topic_published' => 'Konusu yayinlandi',
        ];
    }
}

if (!function_exists('eventsAllowedTaskTypes')) {
    function eventsAllowedTaskTypes(): array
    {
        return [
            'daily' => 'Günlük',
            'weekly' => 'Haftalık',
            'monthly' => 'Aylık',
            'achievement' => 'Başarı',
        ];
    }
}

if (!function_exists('eventsAllowedTaskRewardTypes')) {
    function eventsAllowedTaskRewardTypes(): array
    {
        return [
            'points' => 'Puan',
            'wheel_spin' => 'Çark hakkı',
            'raffle_entry' => 'Çekiliş bileti',
            'coupon' => 'Kupon',
            'custom' => 'Özel ödül',
        ];
    }
}

if (!function_exists('eventsTaskPeriodKey')) {
    function eventsTaskPeriodKey(string $taskType, ?string $time = null): string
    {
        $date = new DateTimeImmutable($time ?: 'now');

        return match ($taskType) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->format('o-\WW'),
            'monthly' => $date->format('Y-m'),
            default => 'all',
        };
    }
}

if (!function_exists('eventsActivityDedupeKey')) {
    function eventsActivityDedupeKey(int $userId, string $activityType, string $subjectType, int $subjectId): string
    {
        return mb_substr($activityType . ':' . $subjectType . ':' . $subjectId . ':user:' . $userId, 0, 191);
    }
}

if (!function_exists('eventsActivityRepeatPolicies')) {
    function eventsActivityRepeatPolicies(): array
    {
        return ['once_per_subject', 'once_per_subject_per_day', 'limited'];
    }
}

if (!function_exists('eventsActivityRepeatPolicyOptions')) {
    function eventsActivityRepeatPolicyOptions(): array
    {
        return [
            'once_per_subject' => [
                'label' => 'Aynı içerik için bir kez',
                'description' => 'Kullanıcı aynı konu, yorum veya hedef için sadece bir kez puan alır.',
            ],
            'once_per_subject_per_day' => [
                'label' => 'Aynı içerik için günde bir kez',
                'description' => 'Aynı kullanıcı aynı hedef için her gün en fazla bir kez puan alır.',
            ],
            'limited' => [
                'label' => 'Limitler belirlesin',
                'description' => 'Tekrarları günlük, haftalık, aylık ve cooldown limitleri kontrol eder.',
            ],
        ];
    }
}

if (!function_exists('eventsActivityRepeatPolicyLabel')) {
    function eventsActivityRepeatPolicyLabel(string $policy): string
    {
        $options = eventsActivityRepeatPolicyOptions();

        return (string)($options[$policy]['label'] ?? $policy);
    }
}

if (!function_exists('eventsActivityRepeatPolicyDescription')) {
    function eventsActivityRepeatPolicyDescription(string $policy): string
    {
        $options = eventsActivityRepeatPolicyOptions();

        return (string)($options[$policy]['description'] ?? '');
    }
}

if (!function_exists('eventsNormalizeActivityDateTime')) {
    function eventsNormalizeActivityDateTime(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime(str_replace('T', ' ', $value));

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}

if (!function_exists('eventsActivityDedupeKeyForPolicy')) {
    function eventsActivityDedupeKeyForPolicy(int $userId, string $activityType, string $subjectType, int $subjectId, string $repeatPolicy, ?string $time = null): string
    {
        $base = eventsActivityDedupeKey($userId, $activityType, $subjectType, $subjectId);
        if ($repeatPolicy === 'once_per_subject_per_day') {
            $date = (new DateTimeImmutable($time ?: 'now'))->format('Y-m-d');
            return mb_substr($base . ':date:' . $date, 0, 191);
        }
        if ($repeatPolicy === 'limited') {
            return mb_substr($base . ':limited', 0, 191);
        }

        return $base;
    }
}

if (!function_exists('eventsActivityRuleWindowSkipReason')) {
    function eventsActivityRuleWindowSkipReason(array $rule, ?string $time = null): ?string
    {
        $now = strtotime($time ?: 'now') ?: time();
        if (!empty($rule['starts_at']) && $now < (strtotime((string)$rule['starts_at']) ?: 0)) {
            return 'not_started';
        }
        if (!empty($rule['ends_at']) && $now > (strtotime((string)$rule['ends_at']) ?: PHP_INT_MAX)) {
            return 'ended';
        }

        return null;
    }
}

if (!function_exists('eventsActivitySubjectApproved')) {
    function eventsActivitySubjectApproved(array $metadata): bool
    {
        if (array_key_exists('is_approved', $metadata)) {
            return (bool)$metadata['is_approved'];
        }

        $status = strtolower((string)($metadata['status'] ?? ''));

        return in_array($status, ['approved', 'published', 'active'], true);
    }
}

if (!function_exists('eventsActivityUserGateSkipReason')) {
    function eventsActivityUserGateSkipReason(array $rule, array $user, ?string $time = null): ?string
    {
        $requiredGroupId = (int)($rule['required_group_id'] ?? 0);
        $userGroupIds = array_values(array_filter(array_map('intval', (array)($user['group_ids'] ?? [])), static fn(int $id): bool => $id > 0));

        if ($requiredGroupId > 0 && !in_array($requiredGroupId, $userGroupIds, true)) {
            return 'group_not_allowed';
        }

        $minAgeDays = (int)($rule['min_account_age_days'] ?? 0);
        if ($minAgeDays > 0) {
            $createdAt = strtotime((string)($user['created_at'] ?? ''));
            if (!$createdAt) {
                return 'account_too_new';
            }
            $now = strtotime($time ?: 'now') ?: time();
            if ($createdAt > strtotime('-' . $minAgeDays . ' days', $now)) {
                return 'account_too_new';
            }
        }

        return null;
    }
}

if (!function_exists('eventsActivityReversalEnabled')) {
    function eventsActivityReversalEnabled(array $rule): bool
    {
        return !array_key_exists('reversal_enabled', $rule) || (int)$rule['reversal_enabled'] === 1;
    }
}

if (!function_exists('eventsActivityHookDefinitions')) {
    function eventsActivityHookDefinitions(): array
    {
        return [
            'daily_login' => ['wired' => true, 'risk' => 'Dusuk', 'location' => 'login.php'],
            'topic_created' => ['wired' => true, 'risk' => 'Orta', 'location' => 'upload-topic.php'],
            'comment_created' => ['wired' => true, 'risk' => 'Orta', 'location' => 'api/comments.php'],
            'comment_reaction_added' => ['wired' => true, 'risk' => 'Orta', 'location' => 'api/comments.php'],
            'topic_favorite_added' => ['wired' => true, 'risk' => 'Orta', 'location' => 'api/favorites/toggle.php'],
            'wheel_spin' => ['wired' => true, 'risk' => 'Dusuk', 'location' => 'includes/src/Modules/Events/Api/Legacy/wheel-spin.php'],
            'topic_downloaded' => ['wired' => true, 'risk' => 'Orta', 'location' => 'includes/src/Engine/Topics/Legacy/helpers.php'],
            'user_registered' => ['wired' => true, 'risk' => 'Dusuk', 'location' => 'register.php'],
            'profile_avatar_uploaded' => ['wired' => true, 'risk' => 'Dusuk', 'location' => 'includes/src/Engine/Users/Legacy/profile-helpers.php'],
            'collection_created' => ['wired' => true, 'risk' => 'Dusuk', 'location' => 'includes/src/Engine/Topics/Legacy/helpers.php'],
            'topic_rated' => ['wired' => false, 'risk' => 'Orta', 'location' => 'rating write endpoint'],
            'topic_viewed' => ['wired' => false, 'risk' => 'Yuksek', 'location' => 'topic.php'],
            'topic_updated' => ['wired' => false, 'risk' => 'Orta', 'location' => 'admin/edit.php'],
            'topic_reported' => ['wired' => false, 'risk' => 'Yuksek', 'location' => 'includes/init.php submitTopicReport'],
            'user_reported' => ['wired' => false, 'risk' => 'Yuksek', 'location' => 'includes/src/Modules/Reports/Legacy/helpers.php'],
            'comment_mention_added' => ['wired' => false, 'risk' => 'Yuksek', 'location' => 'api/comments.php'],
            'collection_item_added' => ['wired' => false, 'risk' => 'Orta', 'location' => 'topic collection helper'],
            'topic_published' => ['wired' => false, 'risk' => 'Dusuk', 'location' => 'admin moderation flow'],
        ];
    }
}

if (!function_exists('eventsActivityHookCatalog')) {
    function eventsActivityHookCatalog(array $rulesByType): array
    {
        $labels = eventsAllowedActivityTypes();
        $definitions = eventsActivityHookDefinitions();
        $catalog = [];

        foreach ($labels as $type => $label) {
            $rule = $rulesByType[$type] ?? null;
            $wired = (bool)($definitions[$type]['wired'] ?? false);
            $hasRule = is_array($rule);
            $isRuleActive = $hasRule && (int)($rule['is_active'] ?? 0) === 1;

            if ($wired && $isRuleActive) {
                $status = 'Aktif';
            } elseif ($hasRule && !$wired && $isRuleActive) {
                $status = 'Kural var, hook yok';
            } elseif (!$hasRule && $wired) {
                $status = 'Hook var, kural yok';
            } else {
                $status = 'Eklenebilir';
            }

            $catalog[$type] = [
                'type' => $type,
                'label' => $label,
                'status_label' => $status,
                'wired' => $wired,
                'has_rule' => $hasRule,
                'risk' => (string)($definitions[$type]['risk'] ?? 'Orta'),
                'location' => (string)($definitions[$type]['location'] ?? ''),
            ];
        }

        return $catalog;
    }
}

if (!function_exists('eventsTaskRequirementComplete')) {
    function eventsTaskRequirementComplete(int $progress, int $target): bool
    {
        return $progress >= max(1, $target);
    }
}

if (!function_exists('eventsTasksTablesReady')) {
    function eventsTasksTablesReady(?PDO $pdo): bool
    {
        if (!$pdo || !eventsTablesReady($pdo)) {
            return false;
        }

        try {
            foreach (['events_activity_rules', 'events_point_ledger', 'events_tasks', 'events_task_requirements', 'events_user_task_progress', 'events_task_claims', 'events_user_bonus_spins'] as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
                if (!$stmt || $stmt->rowCount() < 1) {
                    return false;
                }
            }
            eventsEnsureGroupScopeSchema($pdo);
            if (!eventsColumnExists($pdo, 'events_tasks', 'group_id') || !eventsColumnExists($pdo, 'events_activity_rules', 'required_group_id')) {
                return false;
            }
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('eventsColumnExists')) {
    function eventsColumnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('eventsEnsureGroupScopeSchema')) {
    function eventsEnsureGroupScopeSchema(PDO $pdo): void
    {
        static $done = [];
        $key = spl_object_id($pdo);
        if (!empty($done[$key])) {
            return;
        }
        $done[$key] = true;

        if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
            return;
        }

        if (function_exists('usersEnsureGroupSchema')) {
            try {
                usersEnsureGroupSchema($pdo);
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }

        try {
            if (!eventsColumnExists($pdo, 'events_tasks', 'group_id')) {
                $pdo->exec("ALTER TABLE events_tasks ADD COLUMN group_id BIGINT UNSIGNED DEFAULT NULL AFTER min_user_points");
            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            if (eventsColumnExists($pdo, 'events_tasks', 'group_id')) {
                try {
                    $pdo->exec("ALTER TABLE events_tasks ADD INDEX events_tasks_group_index (group_id)");
                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            if (!eventsColumnExists($pdo, 'events_activity_rules', 'required_group_id')) {
                $pdo->exec("ALTER TABLE events_activity_rules ADD COLUMN required_group_id BIGINT UNSIGNED DEFAULT NULL AFTER min_account_age_days");
            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

        try {
            if (eventsColumnExists($pdo, 'events_activity_rules', 'required_group_id')) {
                try {
                    $pdo->exec("ALTER TABLE events_activity_rules ADD INDEX events_activity_rules_group_index (required_group_id)");
                } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }
}

if (!function_exists('eventsSeedDefaultActivityRules')) {
    function eventsSeedDefaultActivityRules(PDO $pdo): void
    {
        if (!eventsTasksTablesReady($pdo)) {
            return;
        }

        $defaults = [
            ['comment_created', 'Yorum yaptı', 5, 10, 20, 1],
            ['topic_created', 'Konu açtı', 20, 3, 0, 1],
            ['comment_reaction_added', 'Yoruma emoji attı', 1, 20, 0, 0],
            ['topic_favorite_added', 'Konuyu favoriye ekledi', 2, 20, 0, 0],
            ['wheel_spin', 'Çark çevirdi', 1, 5, 0, 1],
            ['daily_login', 'Günlük giriş yaptı', 3, 1, 0, 1],
        ];

        $stmt = $pdo->prepare("INSERT INTO events_activity_rules
            (activity_type, label, points, daily_limit, min_length, allow_self_subject, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE label = VALUES(label), updated_at = updated_at");
        foreach ($defaults as $rule) {
            $stmt->execute($rule);
        }
    }
}

if (!function_exists('eventsNormalizeActivityRuleInput')) {
    function eventsNormalizeActivityRuleInput(array $input): array
    {
        $activityType = (string)($input['activity_type'] ?? 'comment_created');
        if (!array_key_exists($activityType, eventsAllowedActivityTypes())) {
            $activityType = 'comment_created';
        }

        $repeatPolicy = (string)($input['repeat_policy'] ?? 'once_per_subject');
        if (!in_array($repeatPolicy, eventsActivityRepeatPolicies(), true)) {
            $repeatPolicy = 'once_per_subject';
        }

        $requiredGroupId = (int)($input['required_group_id'] ?? 0);

        return [
            'activity_type' => $activityType,
            'label' => trim((string)($input['label'] ?? eventsAllowedActivityTypes()[$activityType])),
            'points' => max(0, min(100000, (int)($input['points'] ?? 0))),
            'daily_limit' => max(0, min(500, (int)($input['daily_limit'] ?? 0))),
            'weekly_limit' => max(0, min(500, (int)($input['weekly_limit'] ?? 0))),
            'monthly_limit' => max(0, min(2000, (int)($input['monthly_limit'] ?? 0))),
            'cooldown_minutes' => max(0, min(10080, (int)($input['cooldown_minutes'] ?? 0))),
            'repeat_policy' => $repeatPolicy,
            'min_length' => max(0, min(10000, (int)($input['min_length'] ?? 0))),
            'min_account_age_days' => max(0, min(3650, (int)($input['min_account_age_days'] ?? 0))),
            'required_group_id' => $requiredGroupId > 0 ? $requiredGroupId : null,
            'allow_self_subject' => !empty($input['allow_self_subject']) ? 1 : 0,
            'requires_approved_subject' => !empty($input['requires_approved_subject']) ? 1 : 0,
            'reversal_enabled' => array_key_exists('reversal_enabled', $input) ? (!empty($input['reversal_enabled']) ? 1 : 0) : 1,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'starts_at' => eventsNormalizeActivityDateTime($input['starts_at'] ?? null),
            'ends_at' => eventsNormalizeActivityDateTime($input['ends_at'] ?? null),
            'admin_note' => mb_substr(trim((string)($input['admin_note'] ?? '')), 0, 500),
        ];
    }
}

if (!function_exists('eventsNormalizeTaskInput')) {
    function eventsNormalizeTaskInput(array $input): array
    {
        $taskType = (string)($input['task_type'] ?? 'daily');
        if (!array_key_exists($taskType, eventsAllowedTaskTypes())) {
            $taskType = 'daily';
        }

        $rewardType = (string)($input['reward_type'] ?? 'points');
        if (!array_key_exists($rewardType, eventsAllowedTaskRewardTypes())) {
            $rewardType = 'points';
        }

        $activityType = (string)($input['activity_type'] ?? 'comment_created');
        if (!array_key_exists($activityType, eventsAllowedActivityTypes())) {
            $activityType = 'comment_created';
        }

        $title = trim((string)($input['title'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        if ($slug === '' && function_exists('slugify')) {
            $slug = slugify($title !== '' ? $title : 'gorev');
        }
        if ($slug === '') {
            $slug = 'gorev-' . date('YmdHis');
        }

        $groupId = trim((string)($input['group_id'] ?? '')) === '' ? null : max(1, (int)$input['group_id']);

        return [
            'id' => max(0, (int)($input['task_id'] ?? ($input['id'] ?? 0))),
            'title' => mb_substr($title !== '' ? $title : 'Yeni görev', 0, 191),
            'slug' => mb_substr(preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)) ?: 'gorev', 0, 191),
            'description' => trim((string)($input['description'] ?? '')),
            'task_type' => $taskType,
            'activity_type' => $activityType,
            'target_count' => max(1, min(100000, (int)($input['target_count'] ?? 1))),
            'reward_type' => $rewardType,
            'reward_value' => mb_substr(trim((string)($input['reward_value'] ?? '')), 0, 191),
            'reward_quantity' => max(1, min(100000, (int)($input['reward_quantity'] ?? 1))),
            'starts_at' => trim((string)($input['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string)($input['ends_at'] ?? '')) ?: null,
            'min_user_points' => trim((string)($input['min_user_points'] ?? '')) === '' ? null : max(0, (int)$input['min_user_points']),
            'group_id' => $groupId,
            'display_order' => (int)($input['display_order'] ?? 0),
            'is_active' => !empty($input['is_active']) ? 1 : 0,
        ];
    }
}

if (!function_exists('eventsRewardLabel')) {
    function eventsRewardLabel(string $type, string $value, int $quantity): string
    {
        $quantity = max(1, $quantity);

        return match ($type) {
            'points' => ((int)$value > 0 ? (int)$value : $quantity) . ' puan',
            'wheel_spin' => $quantity . ' çark hakkı',
            'raffle_entry' => $quantity . ' çekiliş bileti',
            'coupon' => 'Kupon: ' . ($value !== '' ? $value : (string)$quantity),
            'custom' => $value !== '' ? $value : 'Özel ödül',
            default => $quantity . ' ödül',
        };
    }
}

if (!function_exists('eventsMirrorPointDelta')) {
    function eventsMirrorPointDelta(PDO $pdo, int $userId, int $pointsDelta): void
    {
        if ($pointsDelta === 0) {
            return;
        }

        $config = eventsGetConfig($pdo, true);
        $target = eventsValidatePointsTarget($config);
        if (!$target['valid']) {
            return;
        }

        try {
            $table = eventsQuoteSqlIdentifier($target['table']);
            $column = eventsQuoteSqlIdentifier($target['column']);
            $userColumn = eventsQuoteSqlIdentifier($target['user_column']);
            
            $limit = (int)($config['events_max_points_limit'] ?? 0);
            if ($limit > 0 && $pointsDelta > 0) {
                // Fetch current balance
                $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$userColumn} = ? FOR UPDATE");
                $stmt->execute([$userId]);
                $current = (int)$stmt->fetchColumn();
                
                if ($current >= $limit) {
                    return; // Hit limit, do not add points
                }
                
                if ($current + $pointsDelta > $limit) {
                    $pointsDelta = $limit - $current; // Cap the addition
                }
            }
            
            $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = COALESCE({$column}, 0) + ? WHERE {$userColumn} = ?");
            $stmt->execute([$pointsDelta, $userId]);
        } catch (Throwable $e) {
            eventsErrorLog($pdo, 'Events point mirror failed.', ['error' => $e->getMessage(), 'user_id' => $userId], 'WARNING');
        }
    }
}

if (!function_exists('eventsGetPointBalance')) {
    function eventsGetPointBalance(?PDO $pdo, int $userId): int
    {
        if (!$pdo || !eventsTasksTablesReady($pdo) || $userId <= 0) {
            return 0;
        }

        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_delta), 0) FROM events_point_ledger WHERE user_id = ?");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('eventsUserScope')) {
    function eventsUserScope(PDO $pdo, int $userId): array
    {
        $groupIds = [];
        if (function_exists('usersUserGroupIds')) {
            try {
                $groupIds = usersUserGroupIds($pdo, $userId);
            } catch (Throwable $e) {
                $groupIds = [];
            }
        }

        return [
            'group_ids' => array_values(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => $id > 0)),
        ];
    }
}

if (!function_exists('eventsLockUserAccounting')) {
    function eventsLockUserAccounting(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() !== false;
    }
}

if (!function_exists('eventsRecordActivity')) {
    function eventsRecordActivity(PDO $pdo, int $userId, string $activityType, string $subjectType, int $subjectId, array $metadata = []): array
    {
        if ($userId <= 0 || $subjectId <= 0 || !array_key_exists($activityType, eventsAllowedActivityTypes())) {
            return ['success' => false, 'skipped' => 'invalid_activity'];
        }
        if (!eventsTasksTablesReady($pdo)) {
            return ['success' => false, 'skipped' => 'schema_missing'];
        }
        $activityPointsEnabled = eventsActivityPointsEnabled(eventsGetConfig($pdo, true));

        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            if (!eventsLockUserAccounting($pdo, $userId)) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'user_missing'];
            }

            if (!$activityPointsEnabled) {
                eventsUpdateTaskProgressForActivity($pdo, $userId, $activityType);
                eventsAuditLog($pdo, 'activity_record_skipped_points', $subjectType, $subjectId, ['activity_type' => $activityType], $userId);
                if ($started) {
                    $pdo->commit();
                }

                return ['success' => true, 'points' => 0, 'skipped' => 'activity_points_disabled'];
            }

            $ruleStmt = $pdo->prepare("SELECT * FROM events_activity_rules WHERE activity_type = ? LIMIT 1");
            $ruleStmt->execute([$activityType]);
            $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $points = 0;
            if ($rule && (int)$rule['is_active'] === 1) {
                $windowSkip = eventsActivityRuleWindowSkipReason($rule);
                if ($windowSkip !== null) {
                    if ($started) {
                        $pdo->rollBack();
                    }
                    return ['success' => false, 'skipped' => $windowSkip];
                }
                $points = max(0, (int)$rule['points']);
            }

            if ($rule && ((int)($rule['required_group_id'] ?? 0) > 0 || (int)($rule['min_account_age_days'] ?? 0) > 0)) {
                $userStmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ? LIMIT 1");
                $userStmt->execute([$userId]);
                $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$userRow) {
                    if ($started) {
                        $pdo->rollBack();
                    }
                    return ['success' => false, 'skipped' => 'user_missing'];
                }
                $userRow['group_ids'] = eventsUserScope($pdo, $userId)['group_ids'];
                $gateSkip = eventsActivityUserGateSkipReason($rule, $userRow);
                if ($gateSkip !== null) {
                    if ($started) {
                        $pdo->rollBack();
                    }
                    return ['success' => false, 'skipped' => $gateSkip];
                }
            }

            $ownerId = (int)($metadata['subject_user_id'] ?? ($metadata['owner_user_id'] ?? 0));
            if ($rule && (int)$rule['allow_self_subject'] === 0 && $ownerId > 0 && $ownerId === $userId) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'self_subject'];
            }

            if ($rule && (int)($rule['requires_approved_subject'] ?? 0) === 1 && !eventsActivitySubjectApproved($metadata)) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'subject_not_approved'];
            }

            $minLength = $rule ? (int)$rule['min_length'] : 0;
            $textLength = (int)($metadata['text_length'] ?? 0);
            if ($minLength > 0 && $textLength < $minLength) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'min_length'];
            }

            if ($rule && $points > 0) {
                $limitChecks = [
                    ['field' => 'daily_limit', 'sql' => 'created_at >= CURDATE()', 'meta' => 'daily_limited'],
                    ['field' => 'weekly_limit', 'sql' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', 'meta' => 'weekly_limited'],
                    ['field' => 'monthly_limit', 'sql' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', 'meta' => 'monthly_limited'],
                ];
                foreach ($limitChecks as $check) {
                    $limit = (int)($rule[$check['field']] ?? 0);
                    if ($limit <= 0 || $points <= 0) {
                        continue;
                    }
                    $limitStmt = $pdo->prepare("SELECT COUNT(*) FROM events_point_ledger
                        WHERE user_id = ? AND activity_type = ? AND points_delta > 0 AND {$check['sql']}");
                    $limitStmt->execute([$userId, $activityType]);
                    if ((int)$limitStmt->fetchColumn() >= $limit) {
                        $points = 0;
                        $metadata[$check['meta']] = true;
                        if ($check['field'] === 'daily_limit') {
                            $metadata['points_limited'] = true;
                        }
                    }
                }

                $cooldown = (int)($rule['cooldown_minutes'] ?? 0);
                if ($cooldown > 0 && $points > 0) {
                    $cooldownStmt = $pdo->prepare("SELECT created_at FROM events_point_ledger
                        WHERE user_id = ? AND activity_type = ? AND points_delta > 0
                        ORDER BY created_at DESC LIMIT 1");
                    $cooldownStmt->execute([$userId, $activityType]);
                    $lastPaidAt = $cooldownStmt->fetchColumn();
                    if ($lastPaidAt && strtotime((string)$lastPaidAt) > strtotime('-' . $cooldown . ' minutes')) {
                        $points = 0;
                        $metadata['cooldown_limited'] = true;
                    }
                }
            }

            $repeatPolicy = (string)($rule['repeat_policy'] ?? 'once_per_subject');
            $dedupeKey = (string)($metadata['dedupe_key'] ?? eventsActivityDedupeKeyForPolicy($userId, $activityType, $subjectType, $subjectId, $repeatPolicy));
            $ledgerStmt = $pdo->prepare("INSERT INTO events_point_ledger
                (user_id, source_type, source_id, activity_type, points_delta, subject_type, subject_id, dedupe_key, metadata, created_at)
                VALUES (?, 'activity', NULL, ?, ?, ?, ?, ?, ?, NOW())");
            $ledgerStmt->execute([
                $userId,
                $activityType,
                $points,
                $subjectType,
                $subjectId,
                mb_substr($dedupeKey, 0, 191),
                $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $ledgerId = (int)$pdo->lastInsertId();

            if ($points !== 0) {
                eventsMirrorPointDelta($pdo, $userId, $points);
            }

            eventsUpdateTaskProgressForActivity($pdo, $userId, $activityType);
            eventsAuditLog($pdo, 'activity_record', $subjectType, $subjectId, ['activity_type' => $activityType, 'points' => $points], $userId);

            if ($started) {
                $pdo->commit();
            }

            return ['success' => true, 'points' => $points, 'ledger_id' => $ledgerId];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), 'Duplicate') || (string)$e->getCode() === '23000') {
                return ['success' => false, 'skipped' => 'duplicate'];
            }
            eventsErrorLog($pdo, 'Activity recording failed.', ['error' => $e->getMessage(), 'activity_type' => $activityType], 'WARNING');
            return ['success' => false, 'skipped' => 'error'];
        }
    }
}

if (!function_exists('eventsReverseActivityPoints')) {
    function eventsReverseActivityPoints(PDO $pdo, int $userId, string $activityType, string $subjectType, int $subjectId, string $reason): array
    {
        if ($userId <= 0 || !eventsTasksTablesReady($pdo)) {
            return ['success' => false, 'skipped' => 'invalid_request'];
        }

        $dedupeKey = eventsActivityDedupeKey($userId, $activityType, $subjectType, $subjectId);
        $reversalKey = mb_substr('reversal:' . $dedupeKey, 0, 191);
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $ruleStmt = $pdo->prepare("SELECT reversal_enabled FROM events_activity_rules WHERE activity_type = ? LIMIT 1");
            $ruleStmt->execute([$activityType]);
            $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!eventsActivityReversalEnabled($rule)) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'reversal_disabled'];
            }

            $stmt = $pdo->prepare("SELECT * FROM events_point_ledger WHERE dedupe_key = ? AND points_delta > 0 LIMIT 1 FOR UPDATE");
            $stmt->execute([$dedupeKey]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$original) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'original_missing'];
            }

            $exists = $pdo->prepare("SELECT id FROM events_point_ledger WHERE dedupe_key = ? LIMIT 1");
            $exists->execute([$reversalKey]);
            if ($exists->fetchColumn()) {
                if ($started) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'skipped' => 'already_reversed'];
            }

            $points = -1 * abs((int)$original['points_delta']);
            $insert = $pdo->prepare("INSERT INTO events_point_ledger
                (user_id, source_type, source_id, activity_type, points_delta, subject_type, subject_id, dedupe_key, metadata, created_at)
                VALUES (?, 'reversal', ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert->execute([
                $userId,
                (int)$original['id'],
                $activityType,
                $points,
                $subjectType,
                $subjectId,
                $reversalKey,
                json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            eventsMirrorPointDelta($pdo, $userId, $points);
            eventsAuditLog($pdo, 'activity_points_reversal', $subjectType, $subjectId, ['activity_type' => $activityType, 'points' => $points, 'reason' => $reason], $userId);

            if ($started) {
                $pdo->commit();
            }

            return ['success' => true, 'points' => $points];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            eventsErrorLog($pdo, 'Activity point reversal failed.', ['error' => $e->getMessage(), 'activity_type' => $activityType], 'WARNING');
            return ['success' => false, 'skipped' => 'error'];
        }
    }
}

if (!function_exists('eventsUpdateTaskProgressForActivity')) {
    function eventsUpdateTaskProgressForActivity(PDO $pdo, int $userId, string $activityType): void
    {
        $scope = eventsUserScope($pdo, $userId);
        $balance = eventsGetPointBalance($pdo, $userId);
        $stmt = $pdo->prepare("SELECT t.*, r.id AS requirement_id, r.target_count
            FROM events_tasks t
            JOIN events_task_requirements r ON r.task_id = t.id
            WHERE t.is_active = 1
              AND r.activity_type = ?
              AND (t.starts_at IS NULL OR t.starts_at <= NOW())
              AND (t.ends_at IS NULL OR t.ends_at >= NOW())
            ORDER BY t.display_order ASC, t.id ASC");
        $stmt->execute([$activityType]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($tasks as $task) {
            if (!eventsTaskQueryEligible($task, $scope, $balance)) {
                continue;
            }

            $periodKey = eventsTaskPeriodKey((string)$task['task_type']);
            $progressStmt = $pdo->prepare("SELECT * FROM events_user_task_progress
                WHERE user_id = ? AND task_id = ? AND requirement_id = ? AND period_key = ?
                FOR UPDATE");
            $progressStmt->execute([$userId, (int)$task['id'], (int)$task['requirement_id'], $periodKey]);
            $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
            $nextCount = $progress ? ((int)$progress['progress_count'] + 1) : 1;
            $isComplete = eventsTaskRequirementComplete($nextCount, (int)$task['target_count']);

            if ($progress) {
                $updateStmt = $pdo->prepare("UPDATE events_user_task_progress
                    SET progress_count = ?, is_completed = ?, completed_at = IF(? = 1 AND completed_at IS NULL, NOW(), completed_at),
                        last_activity_at = NOW(), updated_at = NOW()
                    WHERE id = ?");
                $updateStmt->execute([$nextCount, $isComplete ? 1 : 0, $isComplete ? 1 : 0, (int)$progress['id']]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO events_user_task_progress
                    (user_id, task_id, requirement_id, period_key, progress_count, is_completed, completed_at, last_activity_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())");
                $insertStmt->execute([
                    $userId,
                    (int)$task['id'],
                    (int)$task['requirement_id'],
                    $periodKey,
                    $nextCount,
                    $isComplete ? 1 : 0,
                    $isComplete ? date('Y-m-d H:i:s') : null,
                ]);
            }

            if (eventsTaskFullyCompleted($pdo, $userId, (int)$task['id'], $periodKey)) {
                $pdo->prepare("UPDATE events_user_task_progress
                    SET is_completed = 1, completed_at = IF(completed_at IS NULL, NOW(), completed_at), updated_at = NOW()
                    WHERE user_id = ? AND task_id = ? AND period_key = ?")
                    ->execute([$userId, (int)$task['id'], $periodKey]);
            }
        }
    }
}

if (!function_exists('eventsTaskFullyCompleted')) {
    function eventsTaskFullyCompleted(PDO $pdo, int $userId, int $taskId, string $periodKey): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(p.progress_count, 0) >= r.target_count THEN 1 ELSE 0 END) AS complete_count
            FROM events_task_requirements r
            LEFT JOIN events_user_task_progress p
                ON p.requirement_id = r.id AND p.user_id = ? AND p.task_id = r.task_id AND p.period_key = ?
            WHERE r.task_id = ?");
        $stmt->execute([$userId, $periodKey, $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'complete_count' => 0];

        return (int)$row['total'] > 0 && (int)$row['total'] === (int)$row['complete_count'];
    }
}

if (!function_exists('eventsTaskQueryEligible')) {
    function eventsTaskQueryEligible(array $task, array $scope, int $balance): bool
    {
        $groupId = (int)($task['group_id'] ?? 0);
        if ($groupId > 0) {
            $groupIds = array_values(array_filter(array_map('intval', (array)($scope['group_ids'] ?? [])), static fn(int $id): bool => $id > 0));
            if (!in_array($groupId, $groupIds, true)) {
                return false;
            }
        }
        if ($task['min_user_points'] !== null && $balance < (int)$task['min_user_points']) {
            return false;
        }
        return true;
    }
}

if (!function_exists('eventsGetUserTasks')) {
    function eventsGetUserTasks(?PDO $pdo, int $userId, ?string $now = null): array
    {
        $groups = ['daily' => [], 'weekly' => [], 'monthly' => [], 'achievement' => []];
        if (!$pdo || !eventsTasksTablesReady($pdo) || $userId <= 0) {
            return ['balance' => 0, 'groups' => $groups, 'history' => []];
        }

        $scope = eventsUserScope($pdo, $userId);
        $balance = eventsGetPointBalance($pdo, $userId);
        $stmt = $pdo->query("SELECT t.*, r.id AS requirement_id, r.activity_type, r.target_count
            FROM events_tasks t
            JOIN events_task_requirements r ON r.task_id = t.id
            WHERE t.is_active = 1
              AND (t.starts_at IS NULL OR t.starts_at <= NOW())
              AND (t.ends_at IS NULL OR t.ends_at >= NOW())
            ORDER BY t.display_order ASC, t.id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            if (!eventsTaskQueryEligible($row, $scope, $balance)) {
                continue;
            }
            $periodKey = eventsTaskPeriodKey((string)$row['task_type'], $now);
            $progressStmt = $pdo->prepare("SELECT progress_count, is_completed FROM events_user_task_progress
                WHERE user_id = ? AND task_id = ? AND requirement_id = ? AND period_key = ? LIMIT 1");
            $progressStmt->execute([$userId, (int)$row['id'], (int)$row['requirement_id'], $periodKey]);
            $progress = $progressStmt->fetch(PDO::FETCH_ASSOC) ?: ['progress_count' => 0, 'is_completed' => 0];

            $claimStmt = $pdo->prepare("SELECT id FROM events_task_claims WHERE user_id = ? AND task_id = ? AND period_key = ? LIMIT 1");
            $claimStmt->execute([$userId, (int)$row['id'], $periodKey]);
            $claimed = (bool)$claimStmt->fetchColumn();
            $current = min((int)$progress['progress_count'], (int)$row['target_count']);
            $completed = eventsTaskRequirementComplete((int)$progress['progress_count'], (int)$row['target_count']);

            $groups[(string)$row['task_type']][] = [
                'id' => (int)$row['id'],
                'title' => (string)$row['title'],
                'description' => (string)($row['description'] ?? ''),
                'task_type' => (string)$row['task_type'],
                'activity_type' => (string)$row['activity_type'],
                'activity_label' => eventsAllowedActivityTypes()[(string)$row['activity_type']] ?? (string)$row['activity_type'],
                'period_key' => $periodKey,
                'progress_count' => $current,
                'target_count' => (int)$row['target_count'],
                'progress_percent' => min(100, (int)floor(($current / max(1, (int)$row['target_count'])) * 100)),
                'is_completed' => $completed,
                'is_claimed' => $claimed,
                'reward_type' => (string)$row['reward_type'],
                'reward_value' => (string)($row['reward_value'] ?? ''),
                'reward_quantity' => (int)$row['reward_quantity'],
                'reward_label' => eventsRewardLabel((string)$row['reward_type'], (string)($row['reward_value'] ?? ''), (int)$row['reward_quantity']),
            ];
        }

        return ['balance' => $balance, 'groups' => $groups, 'history' => eventsGetActivityHistory($pdo, $userId, 8)];
    }
}

if (!function_exists('eventsGetActivityHistory')) {
    function eventsGetActivityHistory(?PDO $pdo, int $userId, int $limit = 20): array
    {
        if (!$pdo || !eventsTasksTablesReady($pdo) || $userId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare("SELECT * FROM events_point_ledger WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('eventsClaimTaskReward')) {
    function eventsClaimTaskReward(PDO $pdo, int $userId, int $taskId, ?string $periodKey = null): array
    {
        if ($userId <= 0 || $taskId <= 0 || !eventsTasksTablesReady($pdo)) {
            return ['success' => false, 'error' => 'invalid_request', 'message' => 'Görev isteği geçersiz.'];
        }

        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $taskStmt = $pdo->prepare("SELECT * FROM events_tasks WHERE id = ? AND is_active = 1 LIMIT 1");
            $taskStmt->execute([$taskId]);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                throw new RuntimeException('Görev bulunamadı.');
            }

            if (!eventsTaskQueryEligible($task, eventsUserScope($pdo, $userId), eventsGetPointBalance($pdo, $userId))) {
                throw new RuntimeException('Bu gorev kullanici grubunuz icin uygun degil.');
            }

            $periodKey = $periodKey ?: eventsTaskPeriodKey((string)$task['task_type']);
            if (!eventsTaskFullyCompleted($pdo, $userId, $taskId, $periodKey)) {
                throw new RuntimeException('Görev henüz tamamlanmadı.');
            }

            $existsStmt = $pdo->prepare("SELECT id FROM events_task_claims WHERE user_id = ? AND task_id = ? AND period_key = ? LIMIT 1");
            $existsStmt->execute([$userId, $taskId, $periodKey]);
            if ($existsStmt->fetchColumn()) {
                throw new RuntimeException('Bu görev ödülü daha önce alındı.');
            }

            $claimStmt = $pdo->prepare("INSERT INTO events_task_claims
                (user_id, task_id, period_key, reward_type, reward_value, reward_quantity, claimed_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $claimStmt->execute([
                $userId,
                $taskId,
                $periodKey,
                $task['reward_type'],
                $task['reward_value'],
                (int)$task['reward_quantity'],
            ]);
            $claimId = (int)$pdo->lastInsertId();
            $userRewardId = null;

            $rewardType = (string)$task['reward_type'];
            $rewardValue = (string)($task['reward_value'] ?? '');
            $rewardQuantity = max(1, (int)$task['reward_quantity']);

            if ($rewardType === 'points') {
                $points = max(0, (int)$rewardValue);
                if ($points <= 0) {
                    $points = $rewardQuantity;
                }
                $dedupeKey = 'task_claim:' . $userId . ':' . $taskId . ':' . $periodKey;
                $ledgerStmt = $pdo->prepare("INSERT INTO events_point_ledger
                    (user_id, source_type, source_id, activity_type, points_delta, subject_type, subject_id, dedupe_key, metadata, created_at)
                    VALUES (?, 'task', ?, 'task_reward', ?, 'task', ?, ?, ?, NOW())");
                $ledgerStmt->execute([
                    $userId,
                    $claimId,
                    $points,
                    $taskId,
                    mb_substr($dedupeKey, 0, 191),
                    json_encode(['task_title' => $task['title']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                eventsMirrorPointDelta($pdo, $userId, $points);
            } elseif ($rewardType === 'wheel_spin') {
                eventsGrantBonusSpins($pdo, $userId, $rewardQuantity, 'task', $claimId);
            } elseif ($rewardType === 'raffle_entry') {
                eventsGrantRaffleEntries($pdo, $userId, $rewardValue, $rewardQuantity);
            } else {
                $rewardStmt = $pdo->prepare("INSERT INTO events_user_rewards
                    (user_id, source_type, source_id, reward_name, reward_type, reward_value, status, created_at, updated_at)
                    VALUES (?, 'task', ?, ?, ?, ?, 'pending', NOW(), NOW())");
                $rewardStmt->execute([$userId, $claimId, $task['title'], $rewardType, $rewardValue]);
                $userRewardId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE events_task_claims SET user_reward_id = ? WHERE id = ?")->execute([$userRewardId, $claimId]);
            }

            $notificationStmt = $pdo->prepare("INSERT INTO events_notifications
                (user_id, type, title, message, related_type, related_id, action_url, priority, created_at)
                VALUES (?, 'task_reward', 'Görev ödülü alındı', ?, 'task', ?, ?, 'medium', NOW())");
            $notificationStmt->execute([
                $userId,
                'Görev ödülünüz: ' . eventsRewardLabel($rewardType, $rewardValue, $rewardQuantity),
                $taskId,
                eventsPublicUrl('tasks'),
            ]);

            eventsAuditLog($pdo, 'task_claim', 'task', $taskId, ['claim_id' => $claimId, 'period_key' => $periodKey], $userId);

            if ($started) {
                $pdo->commit();
            }

            return ['success' => true, 'claim_id' => $claimId, 'user_reward_id' => $userRewardId, 'message' => 'Görev ödülü alındı.'];
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'task_claim_failed', 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('eventsGrantRaffleEntries')) {
    function eventsGrantRaffleEntries(PDO $pdo, int $userId, string $rewardValue, int $quantity): void
    {
        $raffleId = (int)$rewardValue;
        if ($raffleId <= 0) {
            $stmt = $pdo->query("SELECT id FROM events_raffles WHERE is_active = 1 AND status = 'active' AND start_date <= NOW() AND end_date >= NOW() ORDER BY end_date ASC LIMIT 1");
            $raffleId = (int)($stmt ? $stmt->fetchColumn() : 0);
        }
        if ($raffleId <= 0) {
            throw new RuntimeException('Aktif çekiliş bulunamadı.');
        }

        $insert = $pdo->prepare("INSERT INTO events_raffle_entries (raffle_id, user_id, entry_type, created_at) VALUES (?, ?, 'automatic', NOW())");
        for ($i = 0; $i < max(1, $quantity); $i++) {
            $insert->execute([$raffleId, $userId]);
        }
    }
}

if (!function_exists('eventsGrantBonusSpins')) {
    function eventsGrantBonusSpins(PDO $pdo, int $userId, int $quantity, string $sourceType, int $sourceId): void
    {
        $quantity = max(1, $quantity);
        $stmt = $pdo->prepare("INSERT INTO events_user_bonus_spins
            (user_id, source_type, source_id, quantity, remaining_quantity, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW())");
        $stmt->execute([$userId, in_array($sourceType, ['task', 'admin'], true) ? $sourceType : 'task', $sourceId, $quantity, $quantity]);
    }
}

if (!function_exists('eventsConsumeBonusSpin')) {
    function eventsConsumeBonusSpin(PDO $pdo, int $userId): ?array
    {
        if (!eventsTasksTablesReady($pdo)) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT * FROM events_user_bonus_spins
            WHERE user_id = ? AND remaining_quantity > 0 AND (expires_at IS NULL OR expires_at >= NOW())
            ORDER BY expires_at IS NULL ASC, expires_at ASC, id ASC
            LIMIT 1 FOR UPDATE");
        $stmt->execute([$userId]);
        $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bonus) {
            return null;
        }

        $pdo->prepare("UPDATE events_user_bonus_spins SET remaining_quantity = remaining_quantity - 1, updated_at = NOW() WHERE id = ? AND remaining_quantity > 0")
            ->execute([(int)$bonus['id']]);

        return $bonus;
    }
}

if (!function_exists('eventsProcessLoginStreak')) {
    function eventsProcessLoginStreak(PDO $pdo, int $userId, array $config): void
    {
        if ($userId <= 0 || !eventsConfigBool($config, 'events_login_streak_enabled')) {
            return;
        }

        try {
            if (!function_exists('runtimeSchemaUpdatesAllowed') || runtimeSchemaUpdatesAllowed()) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS events_user_streaks (user_id BIGINT UNSIGNED PRIMARY KEY, current_streak INT NOT NULL DEFAULT 0, longest_streak INT NOT NULL DEFAULT 0, last_login_date DATE NULL, last_reward_date DATE NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            $started = !$pdo->inTransaction();
            if ($started) {
                $pdo->beginTransaction();
            }

            if (!eventsLockUserAccounting($pdo, $userId)) {
                if ($started) {
                    $pdo->rollBack();
                }
                return;
            }

            $stmt = $pdo->prepare("SELECT * FROM events_user_streaks WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $streak = $stmt->fetch(PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            if (!$streak) {
                $pdo->prepare("INSERT INTO events_user_streaks (user_id, current_streak, longest_streak, last_login_date, created_at, updated_at) VALUES (?, 1, 1, ?, NOW(), NOW())")
                    ->execute([$userId, $today]);
                $streakCount = 1;
                $lastRewardDate = null;
            } else {
                $lastLogin = (string)$streak['last_login_date'];
                if ($lastLogin === $today) {
                    if ($started) {
                        $pdo->commit();
                    }
                    return; // Already processed today
                }

                $streakCount = (int)$streak['current_streak'];
                if ($lastLogin === $yesterday) {
                    $streakCount++;
                } else {
                    $streakCount = 1;
                }

                $longest = max($streakCount, (int)$streak['longest_streak']);
                $pdo->prepare("UPDATE events_user_streaks SET current_streak = ?, longest_streak = ?, last_login_date = ?, updated_at = NOW() WHERE user_id = ?")
                    ->execute([$streakCount, $longest, $today, $userId]);
                $lastRewardDate = $streak['last_reward_date'] ? (string)$streak['last_reward_date'] : null;
            }

            $targetDays = max(1, (int)($config['events_login_streak_days'] ?? 7));
            if ($streakCount >= $targetDays && ($streakCount % $targetDays === 0)) {
                if ($lastRewardDate === $today) {
                    if ($started) {
                        $pdo->commit();
                    }
                    return; // Already rewarded today (safeguard)
                }

                $rewardType = (string)($config['events_login_streak_reward_type'] ?? 'points');
                $rewardValue = (string)($config['events_login_streak_reward_value'] ?? '50');

                if ($rewardType === 'points') {
                    $points = max(1, (int)$rewardValue);
                    $dedupeKey = 'streak_reward:' . $userId . ':' . $today;
                    $ledgerStmt = $pdo->prepare("INSERT IGNORE INTO events_point_ledger (user_id, source_type, source_id, activity_type, points_delta, subject_type, dedupe_key, metadata, created_at) VALUES (?, 'activity', 0, 'login_streak', ?, 'streak', ?, ?, NOW())");
                    $ledgerStmt->execute([$userId, $points, $dedupeKey, json_encode(['streak' => $streakCount])]);
                    if ($ledgerStmt->rowCount() > 0) {
                        eventsMirrorPointDelta($pdo, $userId, $points);
                    }
                } elseif ($rewardType === 'wheel_spin') {
                    $spins = max(1, (int)$rewardValue);
                    eventsGrantBonusSpins($pdo, $userId, $spins, 'task', 0);
                }

                $pdo->prepare("UPDATE events_user_streaks SET last_reward_date = ?, updated_at = NOW() WHERE user_id = ?")->execute([$today, $userId]);
                eventsAuditLog($pdo, 'login_streak_reward', 'streak', $streakCount, ['reward_type' => $rewardType, 'reward_value' => $rewardValue], $userId);
            }
            if ($started) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if (!empty($started) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            eventsErrorLog($pdo, 'Login streak process failed.', ['error' => $e->getMessage()], 'ERROR');
        }
    }
}


