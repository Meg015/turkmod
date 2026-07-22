<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use Throwable;

final class NotificationPreferenceService
{
    /** @return array<string,array<string,mixed>> */
    public function eventDefinitions(): array
    {
        return [
            'comment_on_topic' => [
                'setting_key' => 'notif_event_comment_on_topic',
                'admin_setting' => 'notif_event_comments_enabled',
                'title' => 'Konunuza yeni yorum geldi',
                'message' => '{{actor_name}}, "{{topic_title}}" konunuza yorum yaptı.',
                'type' => 'info',
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-chat-dots',
                'preference_title' => 'Konuma yorum gelince',
                'preference_description' => 'Paylaştığınız konulara yeni yorum yapıldığında bildirim alın.',
            ],
            'comment_reply' => [
                'setting_key' => 'notif_event_comment_reply',
                'admin_setting' => 'notif_event_comments_enabled',
                'title' => 'Yorumunuza yanıt geldi',
                'message' => '{{actor_name}}, "{{topic_title}}" konusundaki yorumunuza yanıt verdi.',
                'type' => 'info',
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-reply',
                'preference_title' => 'Yorumuma yanıt gelince',
                'preference_description' => 'Bir kullanıcı yorumunuza doğrudan yanıt verdiğinde bildirim alın.',
            ],
            'comment_mention' => [
                'setting_key' => 'notif_event_comment_mention',
                'admin_setting' => 'notif_event_mentions_enabled',
                'title' => 'Bir yorumda sizden bahsedildi',
                'message' => '{{actor_name}}, "{{topic_title}}" konusundaki yorumunda sizden bahsetti.',
                'type' => 'info',
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-at',
                'preference_title' => 'Benden bahsedilince',
                'preference_description' => '@kullanıcı adı ile etiketlendiğiniz yorumlar için bildirim alın.',
            ],
            'direct_message_received' => [
                'setting_key' => 'notif_event_direct_message_received',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Yeni mesaj aldınız',
                'message' => '{{actor_name}} size özel mesaj gönderdi.',
                'type' => 'info',
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-chat-left-text',
                'preference_title' => 'Mesaj aldığımda',
                'preference_description' => 'Başka bir üye size özel mesaj gönderdiğinde bildirim alın.',
            ],
            'topic_approved' => [
                'setting_key' => 'notif_event_topic_approved',
                'admin_setting' => 'notif_event_topic_moderation_enabled',
                'title' => 'Konunuz onaylandı',
                'message' => '"{{topic_title}}" konunuz onaylandı ve yayına alındı.',
                'type' => 'success',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-patch-check',
                'preference_title' => 'Konum onaylanınca',
                'preference_description' => 'Moderasyon sonucu konunuz yayına alındığında bildirim alın.',
            ],
            'topic_rejected' => [
                'setting_key' => 'notif_event_topic_rejected',
                'admin_setting' => 'notif_event_topic_moderation_enabled',
                'title' => 'Konunuz reddedildi',
                'message' => '"{{topic_title}}" konunuz reddedildi.{{moderation_note_line}}',
                'type' => 'error',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-x-octagon',
                'preference_title' => 'Konum reddedilince',
                'preference_description' => 'Konunuz reddedildiğinde ve varsa moderasyon notu eklendiğinde bildirim alın.',
            ],
            'topic_revision_requested' => [
                'setting_key' => 'notif_event_topic_revision_requested',
                'admin_setting' => 'notif_event_topic_moderation_enabled',
                'title' => 'Konunuz için revizyon istendi',
                'message' => '"{{topic_title}}" konunuz için düzenleme istendi.{{moderation_note_line}}',
                'type' => 'warning',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-pencil-square',
                'preference_title' => 'Konum için revizyon istenince',
                'preference_description' => 'Konunuzda düzenleme gerektiğinde moderasyon notuyla birlikte bildirim alın.',
            ],
            'comment_approved' => [
                'setting_key' => 'notif_event_comment_approved',
                'admin_setting' => 'notif_event_comments_enabled',
                'title' => 'Yorumunuz onaylandı',
                'message' => '"{{topic_title}}" konusundaki yorumunuz onaylandı.',
                'type' => 'success',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-check2-circle',
                'preference_title' => 'Yorumum onaylanınca',
                'preference_description' => 'Onay bekleyen yorumunuz yayına alındığında bildirim alın.',
            ],
            'comment_edited_by_staff' => [
                'setting_key' => 'notif_event_comment_edited_by_staff',
                'admin_setting' => 'notif_event_comments_enabled',
                'title' => 'Yorumunuz düzenlendi',
                'message' => '“{{topic_title}}” konusundaki yorumunuz {{actor_name}} tarafından düzenlendi.{{moderation_note_line}}',
                'type' => 'warning',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-pencil-square',
                'preference_title' => 'Yorumum yetkili tarafından düzenlenince',
                'preference_description' => 'Yorumunuz bir admin veya yetkili tarafından değiştirildiğinde bildirim alın.',
            ],
            'favorite_topic_comment' => [
                'setting_key' => 'notif_event_favorite_topic_comment',
                'admin_setting' => 'notif_event_favorites_enabled',
                'title' => 'Favori konunuzda yeni yorum var',
                'message' => '{{actor_name}}, favorinizdeki "{{topic_title}}" konusuna yorum yaptı.',
                'type' => 'info',
                'default' => '0',
                'admin_default' => '0',
                'icon' => 'bi-star',
                'preference_title' => 'Favori konuma yorum gelince',
                'preference_description' => 'Favorilere eklediğiniz konulara yeni yorum yapıldığında bildirim alın.',
            ],
            'user_banned' => [
                'setting_key' => 'notif_event_user_banned',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Hesabınız banlandı',
                'message' => '{{moderation_note}}',
                'type' => 'error',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-slash-circle',
                'preference_title' => 'Hesap ban bildirimi',
                'preference_description' => 'Hesabınız banlandığında bildirim alın.',
            ],
            'user_unbanned' => [
                'setting_key' => 'notif_event_user_unbanned',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Ban kaldırıldı',
                'message' => '{{moderation_note}}',
                'type' => 'success',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-check-circle',
                'preference_title' => 'Ban kaldırma bildirimi',
                'preference_description' => 'Banınız kaldırıldığında bildirim alın.',
            ],
            'user_restricted' => [
                'setting_key' => 'notif_event_user_restricted',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Hesabınız kısıtlandı',
                'message' => '{{moderation_note}}',
                'type' => 'warning',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-shield-exclamation',
                'preference_title' => 'Kısıtlama bildirimi',
                'preference_description' => 'Hesabınıza kısıtlama eklendiğinde bildirim alın.',
            ],
            'user_restriction_removed' => [
                'setting_key' => 'notif_event_user_restriction_removed',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Kısıtlama kaldırıldı',
                'message' => '{{moderation_note}}',
                'type' => 'success',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-shield-check',
                'preference_title' => 'Kısıtlama kaldırma bildirimi',
                'preference_description' => 'Hesap kısıtlamanız kaldırıldığında bildirim alın.',
            ],
            'user_group_changed' => [
                'setting_key' => 'notif_event_user_group_changed',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Grubunuz güncellendi',
                'message' => '{{moderation_note}}',
                'type' => 'info',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-person-badge',
                'preference_title' => 'Grup değişikliği bildirimi',
                'preference_description' => 'Hesap grubunuz değiştiğinde bildirim alın.',
            ],
            'ban_appeal_created' => [
                'setting_key' => 'notif_event_ban_appeal_created',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Yeni ban itirazı',
                'message' => '{{moderation_note}}',
                'type' => 'warning',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-envelope-exclamation',
                'preference_title' => 'Yeni ban itirazı',
                'preference_description' => 'Yeni ban itirazı geldiğinde bildirim alın.',
            ],
            'ban_appeal_message_added' => [
                'setting_key' => 'notif_event_ban_appeal_message_added',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Ban itirazına yeni mesaj',
                'message' => '{{moderation_note}}',
                'type' => 'info',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-chat-dots',
                'preference_title' => 'Ban itirazı mesajı',
                'preference_description' => 'Açık ban itirazına kullanıcı mesajı eklendiğinde bildirim alın.',
            ],
            'ban_appeal_updated' => [
                'setting_key' => 'notif_event_ban_appeal_updated',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Ban itirazınız güncellendi',
                'message' => '{{moderation_note}}',
                'type' => 'info',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-envelope-check',
                'preference_title' => 'Ban itirazı bildirimi',
                'preference_description' => 'Ban itirazınız incelendiğinde bildirim alın.',
            ],
            'topic_report_status_updated' => [
                'setting_key' => 'notif_event_topic_report_status_updated',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Konu raporunuz güncellendi',
                'message' => '"{{topic_title}}" raporunuzun durumu "{{report_status}}" olarak güncellendi.{{moderation_note_line}}',
                'type' => 'info',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-flag',
                'preference_title' => 'Konu raporum güncellenince',
                'preference_description' => 'Gönderdiğiniz konu raporunun inceleme durumu değiştiğinde bildirim alın.',
            ],
            'user_report_status_updated' => [
                'setting_key' => 'notif_event_user_report_status_updated',
                'admin_setting' => 'notif_events_enabled',
                'title' => 'Kullanıcı şikayetiniz güncellendi',
                'message' => 'Kullanıcı şikayetinizin durumu "{{report_status}}" olarak güncellendi.{{moderation_note_line}}',
                'type' => 'info',
                'admin_loggable' => true,
                'default' => '1',
                'admin_default' => '1',
                'icon' => 'bi-person-exclamation',
                'preference_title' => 'Kullanıcı şikayetim güncellenince',
                'preference_description' => 'Gönderdiğiniz kullanıcı şikayetinin inceleme durumu değiştiğinde bildirim alın.',
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function eventPreferenceItems(): array
    {
        $items = [];
        foreach ($this->eventDefinitions() as $eventKey => $definition) {
            $items[] = [
                'key' => $definition['setting_key'],
                'icon' => $definition['icon'],
                'title' => $definition['preference_title'],
                'description' => $definition['preference_description'],
                'default' => $definition['default'],
                'event_key' => $eventKey,
            ];
        }

        return $items;
    }

    public function emailEventSettingKey(string $eventKey): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_]+/', '_', $eventKey) ?: $eventKey;
        $normalized = trim($normalized, '_');

        return 'notif_email_event_' . ($normalized !== '' ? $normalized : 'unknown');
    }

    /** @return list<array<string,mixed>> */
    public function emailEventPreferenceItems(): array
    {
        $items = [];
        foreach ($this->eventDefinitions() as $eventKey => $definition) {
            $items[] = [
                'key' => $this->emailEventSettingKey((string) $eventKey),
                'fallback_key' => $definition['setting_key'],
                'icon' => $definition['icon'],
                'title' => $definition['preference_title'],
                'description' => 'Bu olay icin e-posta bildirimi al.',
                'default' => $definition['default'],
                'event_key' => $eventKey,
            ];
        }

        return $items;
    }

    public function bool(array $settings, string $key, string $default = '1'): bool
    {
        $value = $settings[$key] ?? $default;
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /** @param array<string,mixed>|null $definition */
    public function eventAdminLoggable(string $eventKey, ?array $definition = null): bool
    {
        $definition ??= $this->eventDefinitions()[$eventKey] ?? [];
        $value = $definition['admin_loggable'] ?? false;

        return $value === true || $value === 1 || $value === '1';
    }

    /** @return array<string,mixed> */
    public function adminSettings(?PDO $pdo): array
    {
        if (!$pdo || !function_exists('getAdminSettings')) {
            return [];
        }

        try {
            $settings = getAdminSettings($pdo);
            return is_array($settings) ? $settings : [];
        } catch (Throwable $e) {
            error_log('Notification admin settings could not be loaded: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<string,string> */
    public function userSettings(PDO $pdo, int $userId): array
    {
        try {
            $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Throwable $e) {
            error_log('Notification user settings could not be loaded: ' . $e->getMessage());
            return [];
        }
    }

    public function groupEnabled(array $settings, string $groupKey): bool
    {
        $defaults = [
            'notif_group_email' => '1',
            'notif_group_header' => '1',
            'notif_group_events' => '1',
        ];

        return $this->bool($settings, $groupKey, $defaults[$groupKey] ?? '1');
    }

    /** @param array<string,mixed>|null $definition */
    public function eventEnabledForUser(array $settings, string $eventKey, ?array $definition = null): bool
    {
        $definition ??= $this->eventDefinitions()[$eventKey] ?? [];
        $settingKey = (string) ($definition['setting_key'] ?? '');
        $default = (string) ($definition['default'] ?? '1');

        return $settingKey !== '' && $this->bool($settings, $settingKey, $default);
    }

    /** @param array<string,mixed>|null $definition */
    public function emailEventEnabledForUser(array $settings, string $eventKey, ?array $definition = null): bool
    {
        $definition ??= $this->eventDefinitions()[$eventKey] ?? [];
        $default = (string) ($definition['default'] ?? '1');
        $emailSettingKey = $this->emailEventSettingKey($eventKey);
        if (array_key_exists($emailSettingKey, $settings)) {
            return $this->bool($settings, $emailSettingKey, $default);
        }

        $siteSettingKey = (string) ($definition['setting_key'] ?? '');
        if ($siteSettingKey !== '' && array_key_exists($siteSettingKey, $settings)) {
            return $this->bool($settings, $siteSettingKey, $default);
        }

        return $this->bool($settings, $emailSettingKey, $default);
    }

    /** @return list<string> */
    public function enabledTypesForUser(array $settings): array
    {
        $types = ['info', 'success', 'warning', 'error'];
        $enabled = array_values(array_filter($types, function (string $type) use ($settings): bool {
            return $this->bool($settings, 'notif_type_' . $type, '1');
        }));

        $enabled[] = 'system';

        return $enabled;
    }

    /** @return list<string> */
    public function enabledEventKeysForUser(array $settings): array
    {
        if (!$this->groupEnabled($settings, 'notif_group_events')) {
            return [];
        }

        $enabled = [];
        foreach ($this->eventDefinitions() as $eventKey => $definition) {
            if ($this->eventEnabledForUser($settings, (string) $eventKey, $definition)) {
                $enabled[] = (string) $eventKey;
            }
        }

        return $enabled;
    }

    /** @return list<string> */
    public function enabledEmailEventKeysForUser(array $settings): array
    {
        if (
            !$this->groupEnabled($settings, 'notif_group_email')
            || !$this->bool($settings, 'notif_email_updates', '1')
        ) {
            return [];
        }

        $enabled = [];
        foreach ($this->eventDefinitions() as $eventKey => $definition) {
            if ($this->emailEventEnabledForUser($settings, (string) $eventKey, $definition)) {
                $enabled[] = (string) $eventKey;
            }
        }

        return $enabled;
    }

    /** @return array{sql:string,params:list<string>} */
    public function whereSql(array $settings, string $alias = 'n', bool $filterEvents = true, bool $respectUserPreferences = true): array
    {
        $alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) === 1 ? $alias : 'n';
        $params = [];
        $clauses = [];

        if ($respectUserPreferences) {
            $enabledTypes = $this->enabledTypesForUser($settings);
            if ($enabledTypes === []) {
                $clauses[] = '1 = 0';
            } else {
                $clauses[] = "{$alias}.type IN (" . implode(',', array_fill(0, count($enabledTypes), '?')) . ')';
                $params = array_merge($params, $enabledTypes);
            }
        }

        if ($filterEvents) {
            $clauses[] = "({$alias}.delivery_channels IS NULL OR {$alias}.delivery_channels = '' OR {$alias}.delivery_channels LIKE '%\"in_app\"%')";

            if ($respectUserPreferences) {
                if (!$this->groupEnabled($settings, 'notif_group_events')) {
                    $clauses[] = "({$alias}.event_key IS NULL OR {$alias}.event_key = '')";
                } else {
                    $enabledEvents = $this->enabledEventKeysForUser($settings);
                    if ($enabledEvents === []) {
                        $clauses[] = "({$alias}.event_key IS NULL OR {$alias}.event_key = '')";
                    } else {
                        $clauses[] = "({$alias}.event_key IS NULL OR {$alias}.event_key = '' OR {$alias}.event_key IN (" . implode(',', array_fill(0, count($enabledEvents), '?')) . '))';
                        $params = array_merge($params, $enabledEvents);
                    }
                }
            }
        }

        return [
            'sql' => $clauses === [] ? '' : ' AND ' . implode(' AND ', $clauses),
            'params' => $params,
        ];
    }
}
