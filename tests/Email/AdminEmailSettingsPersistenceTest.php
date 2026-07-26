<?php

declare(strict_types=1);

namespace Tests\Email;

use App\Core\AppSettings;
use App\Engine\Email\AdminEmailService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminEmailSettingsPersistenceTest extends TestCase
{
    public function testEveryAdminEmailCatalogFieldHasASettingDefinition(): void
    {
        $definitions = AdminEmailService::settingDefinitions();

        foreach (AdminEmailService::catalog() as $templateKey => $template) {
            foreach (['enabled', 'subject', 'body', 'action_label'] as $field) {
                $settingKey = AdminEmailService::settingKey($templateKey, $field);

                self::assertArrayHasKey($settingKey, $definitions);
                self::assertSame((string) $template[$field], (string) $definitions[$settingKey]['default']);
                self::assertSame('email', $definitions[$settingKey]['section']);
            }
        }
    }

    public function testRegistrationAdminEmailDefinitionDeclaresLegacyFallback(): void
    {
        $settingKey = AdminEmailService::settingKey('registration_admin_notice', 'enabled');
        $definition = AdminEmailService::settingDefinitions()[$settingKey] ?? [];

        self::assertSame('notif_admin_registration_email_enabled', $definition['legacy_key'] ?? null);
    }

    public function testLegacyValueIsUsedOnlyUntilCanonicalValueIsPersisted(): void
    {
        $settingKey = AdminEmailService::settingKey('registration_admin_notice', 'enabled');
        $legacyKey = 'notif_admin_registration_email_enabled';
        $definitions = [
            $settingKey => [
                'type' => 'bool',
                'default' => '1',
                'legacy_key' => $legacyKey,
            ],
            $legacyKey => [
                'type' => 'bool',
                'default' => '1',
            ],
        ];
        $settings = [
            $settingKey => '1',
            $legacyKey => '0',
        ];

        $reflection = new ReflectionClass(AppSettings::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('applyLegacyFallbacks');
        $method->setAccessible(true);

        $legacyResult = $method->invoke($instance, $settings, $definitions, [$legacyKey => true]);
        self::assertSame('0', $legacyResult[$settingKey]);

        $canonicalResult = $method->invoke($instance, $settings, $definitions, [
            $legacyKey => true,
            $settingKey => true,
        ]);
        self::assertSame('1', $canonicalResult[$settingKey]);
    }

    public function testNotificationModuleDefinesAllEditableRegistrationSiteFields(): void
    {
        $module = require dirname(__DIR__, 2) . '/includes/src/Modules/Notifications/module.php';
        $config = is_array($module['config'] ?? null) ? $module['config'] : [];
        $expectedDefaults = [
            'notif_admin_registration_site_enabled' => '1',
            'notif_admin_registration_site_name' => 'Yeni Kullanıcı Kaydı Admin Bildirimi',
            'notif_admin_registration_site_description' => 'Yeni üyelik oluştuğunda admin ve yetkili hesapların bildirim merkezine düşer.',
            'notif_admin_registration_site_type' => 'system',
            'notif_admin_registration_site_title_template' => 'Yeni kullanıcı kaydı',
            'notif_admin_registration_site_message_template' => '{{username}} ({{email}}) yeni hesap oluşturdu. Durum: {{user_status}}',
            'notif_admin_registration_site_link_template' => '{{admin_link}}',
        ];

        foreach ($expectedDefaults as $key => $default) {
            self::assertArrayHasKey($key, $config);
            self::assertSame($default, (string) ($config[$key]['default'] ?? ''));
        }
    }

    public function testEmailQueueSettingsSchemaDoesNotContainDuplicateRegistrationSwitch(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/notifications.php');
        self::assertIsString($source);
        self::assertMatchesRegularExpression(
            '/function admin_notification_email_settings_schema\(\): array(?<schema>.*?)function admin_notification_account_email_anchor/s',
            $source
        );
        preg_match(
            '/function admin_notification_email_settings_schema\(\): array(?<schema>.*?)function admin_notification_account_email_anchor/s',
            $source,
            $matches
        );

        self::assertStringNotContainsString('notif_admin_registration_email_enabled', (string) ($matches['schema'] ?? ''));
    }

    public function testAdminEmailTemplateUsesPersistedCopyAndEnabledValue(): void
    {
        $templateKey = 'registration_admin_notice';
        $settings = [
            AdminEmailService::settingKey($templateKey, 'enabled') => '0',
            AdminEmailService::settingKey($templateKey, 'subject') => 'Özel konu',
            AdminEmailService::settingKey($templateKey, 'body') => 'Özel içerik',
            AdminEmailService::settingKey($templateKey, 'action_label') => 'Kaydı Aç',
        ];

        $template = (new AdminEmailService())->template($templateKey, $settings);

        self::assertSame('0', $template['enabled']);
        self::assertSame('Özel konu', $template['subject']);
        self::assertSame('Özel içerik', $template['body']);
        self::assertSame('Kaydı Aç', $template['action_label']);
    }
}
