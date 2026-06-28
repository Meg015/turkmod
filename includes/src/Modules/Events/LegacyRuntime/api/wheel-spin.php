<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['POST']);
$userId = eventsApiRequireAuth();
$payload = eventsApiPayload();
eventsApiVerifyCsrf($payload);
eventsApiEnsureReady($pdo, 'wheel');

$config = eventsGetConfig($pdo, true);
$dailyLimit = max(0, (int)$config['wheel_daily_limit']);
$hourlyLimit = max(0, (int)$config['wheel_hourly_limit']);
$now = date('Y-m-d H:i:s');

try {
    $rewardsUrl = function_exists('eventsPublicUrl')
        ? eventsPublicUrl('rewards')
        : (function_exists('routePublicStaticUrl')
            ? routePublicStaticUrl('events', 'rewards')
            : rtrim((string) ($baseUri ?? ''), '/') . '/events/rewards');

    $pdo->beginTransaction();

    // Prevent race conditions by locking the user row for the duration of the transaction
    $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE")->execute([$userId]);

    $wheelUsage = eventsWheelUsageState($pdo, $userId, $config);
    if ((int)$wheelUsage['cooldown_remaining'] > 0) {
        $wait = (int)$wheelUsage['cooldown_remaining'];
        $waitLabel = eventsFormatReadableDurationSeconds($wait, '0 sn');
        $pdo->rollBack();
        header('Retry-After: ' . $wait);
        sendError('cooldown_active', "Çarkı tekrar çevirmek için $waitLabel daha beklemelisiniz.", 429, [
            'retry_after' => $wait,
            'wheel_usage' => $wheelUsage,
        ]);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ? AND created_at >= CURDATE()");
    $stmt->execute([$userId]);
    $dailyCount = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$userId]);
    $hourlyCount = (int)$stmt->fetchColumn();
    
    $cooldownSeconds = max(0, (int)($config['wheel_spin_cooldown_seconds'] ?? 30));
    if ($cooldownSeconds > 0) {
        $stmt = $pdo->prepare("SELECT created_at FROM events_wheel_spins WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $lastSpinDate = $stmt->fetchColumn();
        if ($lastSpinDate) {
            $lastSpinTime = strtotime($lastSpinDate);
            $timeSinceLastSpin = time() - $lastSpinTime;
            if ($timeSinceLastSpin < $cooldownSeconds) {
                $wait = $cooldownSeconds - $timeSinceLastSpin;
                $waitLabel = eventsFormatReadableDurationSeconds($wait, '0 sn');
                $pdo->rollBack();
                sendError('cooldown_active', "Çarkı tekrar çevirmek için $waitLabel daha beklemelisiniz.", 429, [
                    'retry_after' => $wait,
                    'wheel_usage' => $wheelUsage,
                ]);
            }
        }
    }

    $extraSpinCost = (int)($config['wheel_extra_spin_cost'] ?? 0);
    $limitExceeded = false;
    $limitMessage = '';
    $bonusSpin = null;

    if ($dailyLimit > 0 && $dailyCount >= $dailyLimit) {
        $limitExceeded = true;
        $limitMessage = 'Bugün için çark çevirme limitiniz doldu.';
    } elseif ($hourlyLimit > 0 && $hourlyCount >= $hourlyLimit) {
        $limitExceeded = true;
        $limitMessage = 'Saatlik çark çevirme limitiniz doldu.';
    }

    if ($limitExceeded) {
        if (function_exists('eventsConsumeBonusSpin')) {
            $bonusSpin = eventsConsumeBonusSpin($pdo, $userId);
            if ($bonusSpin) {
                $limitExceeded = false;
                eventsAuditLog($pdo, 'bonus_spin_consumed', 'bonus_spin', (int)$bonusSpin['id'], [], $userId);
            }
        }
    }

    if ($limitExceeded) {
        if ($extraSpinCost > 0) {
            $target = eventsValidatePointsTarget($config);
            if (!$target['valid']) {
                $pdo->rollBack();
                sendError('limit_exceeded', $limitMessage . ' Ekstra hak için puan sistemi yapılandırılmamış.', 429, ['wheel_usage' => $wheelUsage]);
            }
            $table = eventsQuoteSqlIdentifier($target['table']);
            $column = eventsQuoteSqlIdentifier($target['column']);
            $userColumn = eventsQuoteSqlIdentifier($target['user_column']);
            
            $pointsStmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$userColumn} = ? FOR UPDATE");
            $pointsStmt->execute([$userId]);
            $userPoints = (int)$pointsStmt->fetchColumn();
            
            if ($userPoints < $extraSpinCost) {
                $pdo->rollBack();
                sendError('insufficient_points', $limitMessage . " Ekstra çevirme için {$extraSpinCost} puana ihtiyacınız var.", 422, ['wheel_usage' => $wheelUsage]);
            }
            
            $pdo->prepare("UPDATE {$table} SET {$column} = {$column} - ? WHERE {$userColumn} = ?")->execute([$extraSpinCost, $userId]);
            eventsAuditLog($pdo, 'extra_spin_purchased', 'wheel', null, ['cost' => $extraSpinCost], $userId);
        } else {
            $pdo->rollBack();
            sendError('limit_exceeded', $limitMessage . ' Lütfen daha sonra tekrar deneyin.', 429, ['wheel_usage' => $wheelUsage]);
        }
    }

    $stmt = $pdo->query("SELECT * FROM events_wheel_rewards WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Pity System
    $premiumThreshold = max(0, (float)($config['wheel_pity_threshold'] ?? 10.0));
    $pityTriggerSpins = max(0, (int)($config['wheel_pity_spins'] ?? 5));
    
    $needsPity = false;
    if ($pityTriggerSpins > 0) {
        $pityStmt = $pdo->prepare("SELECT r.probability FROM events_wheel_spins s JOIN events_wheel_rewards r ON r.id = s.reward_id WHERE s.user_id = ? ORDER BY s.id DESC LIMIT ?");
        $pityStmt->execute([$userId, $pityTriggerSpins]);
        $lastSpins = $pityStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($lastSpins) === $pityTriggerSpins) {
            $needsPity = true;
            foreach ($lastSpins as $spin) {
                if ((float)$spin['probability'] <= $premiumThreshold) {
                    $needsPity = false;
                    break;
                }
            }
        }
    }

    $reward = null;
    if ($needsPity) {
        $premiumRewards = array_filter($rewards, fn($r) => (float)$r['probability'] <= $premiumThreshold);
        if (!empty($premiumRewards)) {
            $reward = eventsPickWeightedReward(array_values($premiumRewards));
        }
    }

    // Fallback if pity was triggered but no premium rewards are eligible (e.g. out of stock or insufficient points),
    // or if pity was not triggered at all.
    if (!$reward) {
        $reward = eventsPickWeightedReward($rewards);
    }
    if (!$reward) {
        $pdo->rollBack();
        sendError('wheel_no_reward', 'Çark için uygun aktif ödül bulunamadı.', 422);
    }

    if ($reward['remaining_quantity'] !== null) {
        $stockStmt = $pdo->prepare("UPDATE events_wheel_rewards SET remaining_quantity = remaining_quantity - 1, updated_at = NOW() WHERE id = ? AND remaining_quantity > 0");
        $stockStmt->execute([(int)$reward['id']]);
        if ($stockStmt->rowCount() < 1) {
            $pdo->rollBack();
            sendError('reward_out_of_stock', 'Seçilen ödülün stoğu tükendi, tekrar deneyin.', 409);
        }
        
        $newStock = (int)$reward['remaining_quantity'] - 1;
        $threshold = max(0, (int)($config['reward_low_stock_threshold'] ?? 5));
        if ($newStock <= $threshold && (int)$reward['remaining_quantity'] > $threshold) {
            eventsAuditLog($pdo, 'low_stock_alert', 'wheel_reward', (int)$reward['id'], [
                'reward_name' => $reward['name'],
                'remaining' => $newStock,
                'threshold' => $threshold
            ]);
        }
    }

    $expiresAt = eventsCalculateExpiryAt($reward['expires_in_days'] !== null ? (int)$reward['expires_in_days'] : (int)$config['wheel_reward_expiry_days'], $now);
    $rewardStmt = $pdo->prepare("INSERT INTO events_user_rewards (user_id, source_type, source_id, reward_name, reward_type, reward_value, status, expires_at, created_at, updated_at)
        VALUES (?, 'wheel', NULL, ?, ?, ?, 'pending', ?, NOW(), NOW())");
    $rewardStmt->execute([$userId, $reward['name'], $reward['type'], $reward['value'], $expiresAt]);
    $userRewardId = (int)$pdo->lastInsertId();
    $pointsApplied = false;
    if ((string)$reward['type'] === 'points' && eventsValidatePointsTarget($config)['valid']) {
        $pointsResult = eventsApplyPointsReward($pdo, $userRewardId, $config, $userId);
        $pointsApplied = (bool)($pointsResult['success'] ?? false);
    }

    $spinStmt = $pdo->prepare("INSERT INTO events_wheel_spins (user_id, reward_id, user_reward_id, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())");
    $spinStmt->execute([
        $userId,
        (int)$reward['id'],
        $userRewardId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
    $spinId = (int)$pdo->lastInsertId();

    $updateRewardStmt = $pdo->prepare("UPDATE events_user_rewards SET source_id = ?, updated_at = NOW() WHERE id = ?");
    $updateRewardStmt->execute([$spinId, $userRewardId]);

    if (function_exists('eventsRecordActivity')) {
        eventsRecordActivity($pdo, $userId, 'wheel_spin', 'wheel', $spinId, []);
    }

    // Record wheel spin in point ledger for history tracking
    $rewardDisplayValue = (string)$reward['value'];
    if ((string)$reward['type'] === 'points') {
        $rewardDisplayValue = (string)$reward['value'];
    } elseif ((string)$reward['type'] === 'wheel_spin') {
        $rewardDisplayValue = (string)$reward['quantity'];
    }

    $ledgerStmt = $pdo->prepare("INSERT INTO events_point_ledger
        (user_id, source_type, source_id, activity_type, points_delta, subject_type, subject_id, metadata, created_at)
        VALUES (?, 'wheel', ?, 'wheel_spin', ?, 'wheel', ?, ?, NOW())");
    $ledgerStmt->execute([
        $userId,
        $spinId,
        (int)$rewardDisplayValue,
        (int)$reward['id'],
        json_encode(['reward_name' => $reward['name'], 'reward_type' => $reward['type'], 'reward_value' => $rewardDisplayValue], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $notificationStmt = $pdo->prepare("INSERT INTO events_notifications (user_id, type, title, message, related_type, related_id, action_url, priority, created_at)
        VALUES (?, 'wheel_win', 'Çark ödülü kazandınız', ?, 'reward', ?, ?, 'medium', NOW())");
    $notificationStmt->execute([
        $userId,
        'Kazandığınız ödül: ' . (string)$reward['name'],
        $userRewardId,
        $rewardsUrl,
    ]);

    eventsAuditLog($pdo, 'wheel_spin', 'wheel_reward', (int)$reward['id'], ['user_reward_id' => $userRewardId], $userId);
    $postSpinUsage = eventsWheelUsageState($pdo, $userId, $config);
    $pdo->commit();

    sendSuccess('Çark çevrildi.', [
        'reward' => [
            'id' => (int)$reward['id'],
            'name' => (string)$reward['name'],
            'type' => (string)$reward['type'],
            'value' => (string)$reward['value'],
            'status' => $pointsApplied ? 'claimed' : 'pending',
            'is_premium' => (float)$reward['probability'] <= $premiumThreshold,
        ],
        'remaining_spins' => [
            'daily' => $postSpinUsage['remaining_daily'],
            'hourly' => $postSpinUsage['remaining_hourly'],
        ],
        'wheel_usage' => $postSpinUsage,
        'wheel_settings' => eventsWheelFrontendSettings($config),
        'bonus_spin_consumed' => $bonusSpin !== null,
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    eventsErrorLog($pdo, 'Wheel spin failed.', ['error' => $e->getMessage()], 'ERROR');
    sendServerError('Çark çevrilirken hata oluştu.', $e);
}
