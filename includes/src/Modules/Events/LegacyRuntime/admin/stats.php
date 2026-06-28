<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTablesReady($pdo ?? null);
eventsAdminStyles($baseUri ?? '');
$stats = eventsAdminStats($pdo ?? null);
$config = eventsGetConfig($pdo ?? null, true);
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="stats">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice((bool)$stats['ready']); ?>
    <?php
    eventsAdminPageHero(
        'İstatistik ve Ayar Özeti',
        'Etkinlik sisteminin genel durumunu ve kullanıcıya açık ayarları düzenleme yapmadan okuyun.',
        'bi-graph-up'
    );
    ?>

    <div class="admin-card ui-events-admin-panel ui-panel">
        <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
            <table class="ui-events-table">
                <thead><tr><th>Ayar</th><th>Değer</th></tr></thead>
                <tbody>
                <?php foreach (eventsConfigUiSections() as $section): ?>
                    <?php foreach (($section['fields'] ?? []) as $field): ?>
                        <?php
                        $key = (string)$field['key'];
                        $value = (string)($config[$key] ?? '');
                        if (($field['type'] ?? '') === 'bool') {
                            $value = eventsConfigBool($config, $key) ? 'Açık' : 'Kapalı';
                        }
                        ?>
                        <tr><td><?= e((string)$field['label']) ?></td><td><?= e($value) ?></td></tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
