<?php
/**
 * Enhanced Notification System for Events Module
 */

/**
 * Send toast notification
 */
function eventsSendToast($userId, $message, $type = 'info', $icon = null, $actionUrl = null) {
    global $pdo;

    if (!$pdo instanceof PDO) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO events_notifications (user_id, message, type, icon, action_url, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $userId,
            $message,
            $type,
            $icon,
            $actionUrl
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Send email notification
 */
function eventsSendEmailNotification($userId, $subject, $template, $data = []) {
    global $pdo;

    if (!$pdo instanceof PDO) return false;

    try {
        $config = function_exists('eventsGetConfig') ? eventsGetConfig($pdo, true) : [];
        if (
            function_exists('eventsConfigBool')
            && (
                !eventsConfigBool($config, 'email_notifications_enabled')
                || !eventsConfigBool($config, 'email_queue_enabled')
            )
        ) {
            return false;
        }

        // Get user email
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        // Queue email
        $stmt = $pdo->prepare("
            INSERT INTO events_email_queue (user_id, email, subject, template, data, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $userId,
            $user['email'],
            $subject,
            $template,
            json_encode($data)
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get user notifications
 */
function eventsGetUserNotifications($pdo, $userId, $limit = 20, $offset = 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM events_notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Mark notification as read
 */
function eventsMarkNotificationRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE events_notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$notificationId, $userId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get unread notification count
 */
function eventsGetUnreadNotificationCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM events_notifications
            WHERE user_id = ? AND is_read = 0
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Clear old notifications
 */
function eventsClearOldNotifications($pdo, $daysOld = 30) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM events_notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = 1
        ");

        return $stmt->execute([$daysOld]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get notification preferences
 */
function eventsGetNotificationPreferences($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM events_notification_preferences
            WHERE user_id = ?
        ");

        $stmt->execute([$userId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

        return $prefs ?: [
            'user_id' => $userId,
            'toast_enabled' => 1,
            'email_enabled' => 1,
            'task_completed' => 1,
            'reward_claimed' => 1,
            'raffle_won' => 1,
            'wheel_won' => 1
        ];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Update notification preferences
 */
function eventsUpdateNotificationPreferences($pdo, $userId, $preferences) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO events_notification_preferences (user_id, toast_enabled, email_enabled, task_completed, reward_claimed, raffle_won, wheel_won, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            toast_enabled = VALUES(toast_enabled),
            email_enabled = VALUES(email_enabled),
            task_completed = VALUES(task_completed),
            reward_claimed = VALUES(reward_claimed),
            raffle_won = VALUES(raffle_won),
            wheel_won = VALUES(wheel_won),
            updated_at = NOW()
        ");

        return $stmt->execute([
            $userId,
            $preferences['toast_enabled'] ?? 1,
            $preferences['email_enabled'] ?? 1,
            $preferences['task_completed'] ?? 1,
            $preferences['reward_claimed'] ?? 1,
            $preferences['raffle_won'] ?? 1,
            $preferences['wheel_won'] ?? 1
        ]);
    } catch (Throwable $e) {
        return false;
    }
}
?>
