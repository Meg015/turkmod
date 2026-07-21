<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;
use RuntimeException;
use Throwable;

final class NotificationTemplateService
{
    public function __construct(
        private ?NotificationPreferenceService $preferences = null,
        private ?NotificationSchemaService $schema = null,
    ) {
        $this->preferences ??= new NotificationPreferenceService();
        $this->schema ??= new NotificationSchemaService();
    }

    /** @return array<string,string> */
    public function allowedVariables(): array
    {
        return [
            'actor_name' => 'İşlemi yapan kullanıcı adı',
            'recipient_name' => 'Bildirim alıcısının adı',
            'topic_title' => 'İlgili konu başlığı',
            'comment_excerpt' => 'Yorumdan kısa alıntı',
            'moderation_note' => 'Moderasyon notu',
            'moderation_note_line' => 'Moderasyon notunun cümle içinde kullanımı',
            'report_status' => 'Raporun güncel durum etiketi',
            'site_name' => 'Site adı',
            'link' => 'Bildirim bağlantısı',
        ];
    }

    /** @return array<string,string> */
    public function samplePayload(): array
    {
        $base = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');

        return [
            'actor_name' => 'Mehmet',
            'recipient_name' => 'Kullanıcı',
            'topic_title' => 'Örnek Mod Konusu',
            'comment_excerpt' => 'Bu konu için yeni bir yorum örneği.',
            'moderation_note' => 'Eksik görseli tamamlayıp tekrar gönderebilirsiniz.',
            'moderation_note_line' => ' Not: Eksik görseli tamamlayıp tekrar gönderebilirsiniz.',
            'report_status' => 'incelendi',
            'site_name' => 'Mod Portal',
            'link' => $base . '/konu/ornek-topic-subject',
        ];
    }

    /** @param array<string,mixed> $source */
    private function emailDefaultsFrom(array $source): array
    {
        $title = trim((string) ($source['title_template'] ?? $source['title'] ?? 'Bildirim'));
        $message = trim((string) ($source['message_template'] ?? $source['message'] ?? ''));
        $link = trim((string) ($source['link_template'] ?? '{{link}}'));

        return [
            'email_subject_template' => $title,
            'email_body_template' => "Merhaba {{recipient_name}},\n\n" . $message . "\n\nDetayları görüntülemek için bağlantıyı kullanabilirsiniz.\n\n{{site_name}}",
            'email_link_template' => $link,
            'email_preview_template' => $message !== '' ? mb_substr($message, 0, 255) : $title,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public function defaults(): array
    {
        $samplePayload = $this->samplePayload();
        $variables = array_keys($this->allowedVariables());
        $defaults = [];

        foreach ($this->preferences->eventDefinitions() as $eventKey => $definition) {
            $template = [
                'template_key' => $eventKey,
                'name' => (string) ($definition['preference_title'] ?? $definition['title']),
                'description' => (string) ($definition['preference_description'] ?? ''),
                'type' => (string) ($definition['type'] ?? 'info'),
                'title_template' => (string) ($definition['title'] ?? ''),
                'message_template' => (string) ($definition['message'] ?? ''),
                'link_template' => '{{link}}',
                'in_app_enabled' => 1,
                'email_enabled' => 0,
                'is_active' => 1,
                'variables' => $variables,
                'sample_payload' => $samplePayload,
            ];
            $defaults[$eventKey] = array_merge($template, $this->emailDefaultsFrom($template));
        }

        $manualAnnouncement = [
            'template_key' => 'manual_announcement',
            'name' => 'Genel Duyuru',
            'description' => 'Tüm kullanıcılara veya belirli bir kullanıcıya gönderilecek standart duyuru metni.',
            'type' => 'info',
            'title_template' => 'Duyuru: {{topic_title}}',
            'message_template' => '{{site_name}} duyurusu: {{comment_excerpt}}',
            'link_template' => '{{link}}',
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'is_active' => 1,
            'variables' => $variables,
            'sample_payload' => $samplePayload,
        ];
        $defaults['manual_announcement'] = array_merge($manualAnnouncement, $this->emailDefaultsFrom($manualAnnouncement));

        $manualMaintenance = [
            'template_key' => 'manual_maintenance',
            'name' => 'Bakım Bilgilendirmesi',
            'description' => 'Planlı bakım, kısa kesinti ve sistem duyuruları için uyarı metni.',
            'type' => 'warning',
            'title_template' => 'Planlı bakım duyurusu',
            'message_template' => '{{site_name}} üzerinde planlı bakım yapılacaktır. {{comment_excerpt}}',
            'link_template' => '{{link}}',
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'is_active' => 1,
            'variables' => $variables,
            'sample_payload' => $samplePayload,
        ];
        $defaults['manual_maintenance'] = array_merge($manualMaintenance, $this->emailDefaultsFrom($manualMaintenance));

        $manualSuccessUpdate = [
            'template_key' => 'manual_success_update',
            'name' => 'Başarılı İşlem Bildirimi',
            'description' => 'Tamamlanan işlem, onay veya olumlu sonuç duyuruları için bildirim metni.',
            'type' => 'success',
            'title_template' => 'İşleminiz tamamlandı',
            'message_template' => '{{recipient_name}}, {{comment_excerpt}}',
            'link_template' => '{{link}}',
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'is_active' => 1,
            'variables' => $variables,
            'sample_payload' => $samplePayload,
        ];
        $defaults['manual_success_update'] = array_merge($manualSuccessUpdate, $this->emailDefaultsFrom($manualSuccessUpdate));

        return $defaults;
    }

    public function ensureSchema(PDO $pdo): void
    {
        $this->schema->ensureTemplateSchema($pdo, $this);
    }

    /** @return list<string> */
    public function missingEmailColumns(PDO $pdo): array
    {
        return $this->schema->missingEmailTemplateColumns($pdo, true);
    }

    public function emailColumnsReady(PDO $pdo): bool
    {
        return $this->missingEmailColumns($pdo) === [];
    }

    public function seedDefaultTemplates(PDO $pdo): void
    {
        $availableColumns = $this->schema->templateTableColumns($pdo, true);
        $columns = [
            'template_key',
            'name',
            'description',
            'type',
            'title_template',
            'message_template',
            'link_template',
            'in_app_enabled',
            'email_enabled',
        ];
        foreach (['email_subject_template', 'email_body_template', 'email_link_template', 'email_preview_template'] as $column) {
            if (isset($availableColumns[$column])) {
                $columns[] = $column;
            }
        }
        array_push($columns, 'is_active', 'variables_json', 'sample_payload');

        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare(
            'INSERT INTO notification_templates (' . implode(', ', $quotedColumns) . ', `created_at`, `updated_at`) '
            . 'VALUES (' . $placeholders . ', NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE template_key = VALUES(template_key)'
        );

        foreach ($this->defaults() as $template) {
            $values = [
                'template_key' => $template['template_key'],
                'name' => $template['name'],
                'description' => $template['description'],
                'type' => $template['type'],
                'title_template' => $template['title_template'],
                'message_template' => $template['message_template'],
                'link_template' => $template['link_template'],
                'in_app_enabled' => (int) $template['in_app_enabled'],
                'email_enabled' => (int) $template['email_enabled'],
                'email_subject_template' => $template['email_subject_template'],
                'email_body_template' => $template['email_body_template'],
                'email_link_template' => $template['email_link_template'],
                'email_preview_template' => $template['email_preview_template'],
                'is_active' => (int) $template['is_active'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
                'sample_payload' => json_encode($template['sample_payload'], JSON_UNESCAPED_UNICODE),
            ];
            $stmt->execute(array_map(static fn (string $column): mixed => $values[$column], $columns));
        }
    }

    /** @return array<mixed> */
    public function decodeJson(?string $json, array $fallback): array
    {
        if ($json === null || $json === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $fallback;
    }

    /** @param array<string,mixed> $row */
    public function normalizeRow(array $row): array
    {
        $defaults = $this->defaults();
        $key = (string) ($row['template_key'] ?? '');
        $default = $defaults[$key] ?? [];

        $row['variables'] = $this->decodeJson(
            isset($row['variables_json']) ? (string) $row['variables_json'] : null,
            $default['variables'] ?? array_keys($this->allowedVariables())
        );
        $row['sample_payload_array'] = $this->decodeJson(
            isset($row['sample_payload']) ? (string) $row['sample_payload'] : null,
            $default['sample_payload'] ?? $this->samplePayload()
        );
        $row['is_default'] = isset($defaults[$key]);
        foreach (['email_subject_template', 'email_body_template', 'email_link_template', 'email_preview_template'] as $emailField) {
            if (!array_key_exists($emailField, $row) || trim((string) $row[$emailField]) === '') {
                $row[$emailField] = (string) ($default[$emailField] ?? '');
            }
        }

        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function list(PDO $pdo, bool $onlyActive = false): array
    {
        $this->ensureSchema($pdo);

        $sql = 'SELECT * FROM notification_templates';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1 AND in_app_enabled = 1';
        }
        $sql .= " ORDER BY CASE WHEN template_key LIKE 'manual_%' THEN 1 ELSE 0 END, id ASC";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /** @return array<string,mixed>|null */
    public function get(PDO $pdo, string $templateKey): ?array
    {
        $this->ensureSchema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM notification_templates WHERE template_key = ? LIMIT 1');
        $stmt->execute([$templateKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    /** @return list<string> */
    public function extractVariables(string $template): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @return list<string> */
    public function validate(array $input): array
    {
        $errors = [];
        $validTypes = ['info', 'success', 'warning', 'error', 'system'];

        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors[] = 'Bildirim adı zorunludur.';
        }
        if (trim((string) ($input['title_template'] ?? '')) === '') {
            $errors[] = 'Başlık metni zorunludur.';
        }
        if (trim((string) ($input['message_template'] ?? '')) === '') {
            $errors[] = 'Mesaj metni zorunludur.';
        }
        if (mb_strlen((string) ($input['title_template'] ?? '')) > 255) {
            $errors[] = 'Başlık metni en fazla 255 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['link_template'] ?? '')) > 1024) {
            $errors[] = 'Link metni en fazla 1024 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['email_subject_template'] ?? '')) > 255) {
            $errors[] = 'E-posta konusu en fazla 255 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['email_body_template'] ?? '')) > 10000) {
            $errors[] = 'E-posta gövdesi en fazla 10000 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['email_link_template'] ?? '')) > 1024) {
            $errors[] = 'E-posta link metni en fazla 1024 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['email_preview_template'] ?? '')) > 255) {
            $errors[] = 'E-posta önizleme metni en fazla 255 karakter olabilir.';
        }
        if (!empty($input['email_enabled']) && trim((string) ($input['email_subject_template'] ?? '')) === '') {
            $errors[] = 'E-posta açıkken konu metni zorunludur.';
        }
        if (!empty($input['email_enabled']) && trim((string) ($input['email_body_template'] ?? '')) === '') {
            $errors[] = 'E-posta açıkken gövde metni zorunludur.';
        }
        if (!in_array((string) ($input['type'] ?? 'info'), $validTypes, true)) {
            $errors[] = 'Geçersiz bildirim tipi.';
        }

        $allowedVariables = array_keys($this->allowedVariables());
        $usedVariables = array_unique(array_merge(
            $this->extractVariables((string) ($input['title_template'] ?? '')),
            $this->extractVariables((string) ($input['message_template'] ?? '')),
            $this->extractVariables((string) ($input['link_template'] ?? '')),
            $this->extractVariables((string) ($input['email_subject_template'] ?? '')),
            $this->extractVariables((string) ($input['email_body_template'] ?? '')),
            $this->extractVariables((string) ($input['email_link_template'] ?? '')),
            $this->extractVariables((string) ($input['email_preview_template'] ?? ''))
        ));
        $unknownVariables = array_values(array_diff($usedVariables, $allowedVariables));
        if ($unknownVariables !== []) {
            $errors[] = 'Bilinmeyen değişkenler: ' . implode(', ', $unknownVariables);
        }

        return $errors;
    }

    public function save(PDO $pdo, string $templateKey, array $input): bool
    {
        $this->ensureSchema($pdo);

        if (!preg_match('/^[a-z0-9_]{3,100}$/', $templateKey)) {
            throw new RuntimeException('Bildirim anahtarı küçük harf, rakam ve alt çizgiden oluşmalıdır.');
        }

        $missingEmailColumns = $this->missingEmailColumns($pdo);
        if (($input['channel'] ?? '') === 'email' && $missingEmailColumns !== []) {
            throw new RuntimeException('E-posta metin alanları henüz hazır değil. Admin Panel > Veritabanı Senkronizasyonu çalıştırılmalı.');
        }

        $existing = $this->get($pdo, $templateKey);
        $default = $this->defaults()[$templateKey] ?? null;
        $allowCreate = !empty($input['allow_create']);
        if (!$existing && !$default && !$allowCreate) {
            return false;
        }

        $errors = $this->validate($input);
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $baseTemplate = $existing ?: $default;
        $variables = $baseTemplate['variables'] ?? array_keys($this->allowedVariables());
        $samplePayload = $baseTemplate['sample_payload_array'] ?? $baseTemplate['sample_payload'] ?? $this->samplePayload();
        $boolInput = static function (string $key) use ($input): int {
            return isset($input[$key]) && (string) $input[$key] !== '0' ? 1 : 0;
        };

        $data = [
            'template_key' => $templateKey,
            'name' => trim((string) $input['name']),
            'description' => trim((string) ($input['description'] ?? '')),
            'type' => (string) ($input['type'] ?? 'info'),
            'title_template' => trim((string) $input['title_template']),
            'message_template' => trim((string) $input['message_template']),
            'link_template' => trim((string) ($input['link_template'] ?? '')),
            'in_app_enabled' => $boolInput('in_app_enabled'),
            'email_enabled' => $boolInput('email_enabled'),
            'is_active' => $boolInput('is_active'),
            'variables_json' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'sample_payload' => json_encode($samplePayload, JSON_UNESCAPED_UNICODE),
        ];

        $availableColumns = $this->schema->templateTableColumns($pdo, true);
        foreach (['email_subject_template', 'email_body_template', 'email_link_template', 'email_preview_template'] as $column) {
            if (isset($availableColumns[$column])) {
                $data[$column] = trim((string) ($input[$column] ?? ''));
            }
        }

        $columns = array_keys($data);
        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $updateColumns = array_values(array_filter($columns, static fn (string $column): bool => $column !== 'template_key'));
        $updateSql = implode(', ', array_map(
            static fn (string $column): string => '`' . str_replace('`', '``', $column) . '` = VALUES(`' . str_replace('`', '``', $column) . '`)',
            $updateColumns
        ));

        $stmt = $pdo->prepare(
            'INSERT INTO notification_templates (' . implode(', ', $quotedColumns) . ', `created_at`, `updated_at`) '
            . 'VALUES (' . $placeholders . ', NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE ' . $updateSql . ', updated_at = NOW()'
        );

        return $stmt->execute(array_values($data));
    }

    public function delete(PDO $pdo, string $templateKey): bool
    {
        $this->ensureSchema($pdo);

        if (isset($this->defaults()[$templateKey])) {
            return false;
        }

        $stmt = $pdo->prepare('DELETE FROM notification_templates WHERE template_key = ?');

        return $stmt->execute([$templateKey]);
    }

    public function reset(PDO $pdo, string $templateKey): bool
    {
        $defaults = $this->defaults();
        if (!isset($defaults[$templateKey])) {
            return false;
        }

        return $this->save($pdo, $templateKey, [
            'name' => $defaults[$templateKey]['name'],
            'description' => $defaults[$templateKey]['description'],
            'type' => $defaults[$templateKey]['type'],
            'title_template' => $defaults[$templateKey]['title_template'],
            'message_template' => $defaults[$templateKey]['message_template'],
            'link_template' => $defaults[$templateKey]['link_template'],
            'in_app_enabled' => (string) $defaults[$templateKey]['in_app_enabled'],
            'email_enabled' => (string) $defaults[$templateKey]['email_enabled'],
            'email_subject_template' => $defaults[$templateKey]['email_subject_template'],
            'email_body_template' => $defaults[$templateKey]['email_body_template'],
            'email_link_template' => $defaults[$templateKey]['email_link_template'],
            'email_preview_template' => $defaults[$templateKey]['email_preview_template'],
            'is_active' => (string) $defaults[$templateKey]['is_active'],
        ]);
    }

    /** @return array{title:string,message:string,link:string,type:string} */
    public function preview(array $template, ?array $payload = null): array
    {
        $payload = $payload ?: ($template['sample_payload_array'] ?? $this->samplePayload());

        return [
            'title' => $this->render((string) ($template['title_template'] ?? ''), $payload),
            'message' => $this->render((string) ($template['message_template'] ?? ''), $payload),
            'link' => $this->render((string) ($template['link_template'] ?? ''), $payload),
            'type' => (string) ($template['type'] ?? 'info'),
        ];
    }

    /** @return array{subject:string,body:string,link:string,preview:string,type:string} */
    public function emailPreview(array $template, ?array $payload = null): array
    {
        $payload = $payload ?: ($template['sample_payload_array'] ?? $this->samplePayload());

        return [
            'subject' => $this->render((string) ($template['email_subject_template'] ?? ''), $payload),
            'body' => $this->render((string) ($template['email_body_template'] ?? ''), $payload),
            'link' => $this->render((string) ($template['email_link_template'] ?? ''), $payload),
            'preview' => $this->render((string) ($template['email_preview_template'] ?? ''), $payload),
            'type' => (string) ($template['type'] ?? 'info'),
        ];
    }

    /** @return list<string> */
    public function emailCopyErrors(array $template): array
    {
        $errors = [];
        if (trim((string) ($template['email_subject_template'] ?? '')) === '') {
            $errors[] = 'E-posta konusu eksik.';
        }
        if (trim((string) ($template['email_body_template'] ?? '')) === '') {
            $errors[] = 'E-posta gövdesi eksik.';
        }

        return $errors;
    }

    /** @return array<string,mixed>|null */
    public function forDispatch(PDO $pdo, string $eventKey, array $definition): ?array
    {
        $template = $this->get($pdo, $eventKey);
        if (!$template) {
            return null;
        }

        $inAppEnabled = (int) ($template['in_app_enabled'] ?? 1) === 1;
        $emailEnabled = (int) ($template['email_enabled'] ?? 0) === 1;
        if ((int) ($template['is_active'] ?? 1) !== 1 || (!$inAppEnabled && !$emailEnabled)) {
            return null;
        }

        return $template;
    }

    public function render(string $template, array $payload): string
    {
        return trim((string) preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static function (array $matches) use ($payload): string {
            $value = $payload[$matches[1]] ?? '';

            return is_scalar($value) || $value === null ? (string) $value : '';
        }, $template));
    }

    public function payloadValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        return is_scalar($value) || $value === null ? trim((string) $value) : $default;
    }
}
