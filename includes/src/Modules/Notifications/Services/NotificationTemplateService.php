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
            'actor_name' => 'Islemi yapan kullanici adi',
            'recipient_name' => 'Bildirim alicisinin adi',
            'topic_title' => 'Ilgili konu basligi',
            'comment_excerpt' => 'Yorumdan kisa alinti',
            'moderation_note' => 'Moderasyon notu',
            'moderation_note_line' => 'Moderasyon notunun cumle icinde kullanimi',
            'site_name' => 'Site adi',
            'link' => 'Bildirim baglantisi',
        ];
    }

    /** @return array<string,string> */
    public function samplePayload(): array
    {
        $base = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');

        return [
            'actor_name' => 'Mehmet',
            'recipient_name' => 'Kullanici',
            'topic_title' => 'Ornek Mod Konusu',
            'comment_excerpt' => 'Bu konu icin yeni bir yorum ornegi.',
            'moderation_note' => 'Eksik gorseli tamamlayip tekrar gonderebilirsiniz.',
            'moderation_note_line' => ' Not: Eksik gorseli tamamlayip tekrar gonderebilirsiniz.',
            'site_name' => 'Mod Portal',
            'link' => $base . '/konu/ornek-topic-subject',
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public function defaults(): array
    {
        $samplePayload = $this->samplePayload();
        $variables = array_keys($this->allowedVariables());
        $defaults = [];

        foreach ($this->preferences->eventDefinitions() as $eventKey => $definition) {
            $defaults[$eventKey] = [
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
        }

        $defaults['manual_announcement'] = [
            'template_key' => 'manual_announcement',
            'name' => 'Genel Duyuru',
            'description' => 'Tum kullanicilara veya belirli bir kullaniciya gonderilecek standart duyuru sablonu.',
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

        $defaults['manual_maintenance'] = [
            'template_key' => 'manual_maintenance',
            'name' => 'Bakim Bilgilendirmesi',
            'description' => 'Planli bakim, kisa kesinti ve sistem duyurulari icin uyari sablonu.',
            'type' => 'warning',
            'title_template' => 'Planli bakim duyurusu',
            'message_template' => '{{site_name}} uzerinde planli bakim yapilacaktir. {{comment_excerpt}}',
            'link_template' => '{{link}}',
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'is_active' => 1,
            'variables' => $variables,
            'sample_payload' => $samplePayload,
        ];

        $defaults['manual_success_update'] = [
            'template_key' => 'manual_success_update',
            'name' => 'Basarili Islem Bildirimi',
            'description' => 'Tamamlanan islem, onay veya olumlu sonuc duyurulari icin sablon.',
            'type' => 'success',
            'title_template' => 'Isleminiz tamamlandi',
            'message_template' => '{{recipient_name}}, {{comment_excerpt}}',
            'link_template' => '{{link}}',
            'in_app_enabled' => 1,
            'email_enabled' => 0,
            'is_active' => 1,
            'variables' => $variables,
            'sample_payload' => $samplePayload,
        ];

        return $defaults;
    }

    public function ensureSchema(PDO $pdo): void
    {
        $this->schema->ensureTemplateSchema($pdo, $this);
    }

    public function seedDefaultTemplates(PDO $pdo): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO notification_templates
                (template_key, name, description, type, title_template, message_template, link_template, in_app_enabled, email_enabled, is_active, variables_json, sample_payload, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE template_key = VALUES(template_key)
        ");

        foreach ($this->defaults() as $template) {
            $stmt->execute([
                $template['template_key'],
                $template['name'],
                $template['description'],
                $template['type'],
                $template['title_template'],
                $template['message_template'],
                $template['link_template'],
                (int) $template['in_app_enabled'],
                (int) $template['email_enabled'],
                (int) $template['is_active'],
                json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
                json_encode($template['sample_payload'], JSON_UNESCAPED_UNICODE),
            ]);
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
            $errors[] = 'Sablon adi zorunludur.';
        }
        if (trim((string) ($input['title_template'] ?? '')) === '') {
            $errors[] = 'Baslik sablonu zorunludur.';
        }
        if (trim((string) ($input['message_template'] ?? '')) === '') {
            $errors[] = 'Mesaj sablonu zorunludur.';
        }
        if (mb_strlen((string) ($input['title_template'] ?? '')) > 255) {
            $errors[] = 'Baslik sablonu en fazla 255 karakter olabilir.';
        }
        if (mb_strlen((string) ($input['link_template'] ?? '')) > 1024) {
            $errors[] = 'Link sablonu en fazla 1024 karakter olabilir.';
        }
        if (!in_array((string) ($input['type'] ?? 'info'), $validTypes, true)) {
            $errors[] = 'Gecersiz bildirim tipi.';
        }

        $allowedVariables = array_keys($this->allowedVariables());
        $usedVariables = array_unique(array_merge(
            $this->extractVariables((string) ($input['title_template'] ?? '')),
            $this->extractVariables((string) ($input['message_template'] ?? '')),
            $this->extractVariables((string) ($input['link_template'] ?? ''))
        ));
        $unknownVariables = array_values(array_diff($usedVariables, $allowedVariables));
        if ($unknownVariables !== []) {
            $errors[] = 'Bilinmeyen degiskenler: ' . implode(', ', $unknownVariables);
        }

        return $errors;
    }

    public function save(PDO $pdo, string $templateKey, array $input): bool
    {
        $this->ensureSchema($pdo);

        if (!preg_match('/^[a-z0-9_]{3,100}$/', $templateKey)) {
            throw new RuntimeException('Sablon anahtari kucuk harf, rakam ve alt cizgiden olusmalidir.');
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

        $stmt = $pdo->prepare("
            INSERT INTO notification_templates
                (template_key, name, description, type, title_template, message_template, link_template, in_app_enabled, email_enabled, is_active, variables_json, sample_payload, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                type = VALUES(type),
                title_template = VALUES(title_template),
                message_template = VALUES(message_template),
                link_template = VALUES(link_template),
                in_app_enabled = VALUES(in_app_enabled),
                email_enabled = VALUES(email_enabled),
                is_active = VALUES(is_active),
                variables_json = VALUES(variables_json),
                sample_payload = VALUES(sample_payload),
                updated_at = NOW()
        ");

        return $stmt->execute([
            $templateKey,
            trim((string) $input['name']),
            trim((string) ($input['description'] ?? '')),
            (string) ($input['type'] ?? 'info'),
            trim((string) $input['title_template']),
            trim((string) $input['message_template']),
            trim((string) ($input['link_template'] ?? '')),
            $boolInput('in_app_enabled'),
            $boolInput('email_enabled'),
            $boolInput('is_active'),
            json_encode($variables, JSON_UNESCAPED_UNICODE),
            json_encode($samplePayload, JSON_UNESCAPED_UNICODE),
        ]);
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

    /** @return array<string,mixed>|null */
    public function forDispatch(PDO $pdo, string $eventKey, array $definition): ?array
    {
        $template = $this->get($pdo, $eventKey);
        if (!$template) {
            return null;
        }

        if ((int) ($template['is_active'] ?? 1) !== 1 || (int) ($template['in_app_enabled'] ?? 1) !== 1) {
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
