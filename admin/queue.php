<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('queue.view', 'Bekleyen is kuyrugunu goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Bekleyen İş Kuyruğu';

$queue = [
    'topic_reports'   => 0,
    'user_reports'    => 0,
    'ban_appeals'     => 0,
    'pending_topics'  => 0,
    'pending_comments'=> 0,
    'unread_notifs'   => 0,
];

$samples = [
    'topic_reports'   => [],
    'user_reports'    => [],
    'ban_appeals'     => [],
    'pending_comments'=> [],
];

if ($pdo) {
    try {
        $counts = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM topic_reports WHERE status IN ('open','reviewing')) AS topic_reports,
                (SELECT COUNT(*) FROM user_reports WHERE status IN ('open','reviewing')) AS user_reports,
                (SELECT COUNT(*) FROM topics WHERE status = 'draft' AND deleted_at IS NULL) AS pending_topics,
                (SELECT COUNT(*) FROM comments WHERE status='pending' AND deleted_at IS NULL) AS pending_comments,
                (SELECT COUNT(*) FROM admin_notifications WHERE read_at IS NULL) AS unread_notifs
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $queue['topic_reports']    = (int) ($counts['topic_reports'] ?? 0);
        $queue['user_reports']     = (int) ($counts['user_reports'] ?? 0);
        $queue['pending_topics']   = (int) ($counts['pending_topics'] ?? 0);
        $queue['pending_comments'] = (int) ($counts['pending_comments'] ?? 0);
        $queue['unread_notifs']    = (int) ($counts['unread_notifs'] ?? 0);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        if (function_exists('usersGetBanAppealStats')) {
            $a = usersGetBanAppealStats($pdo);
            $queue['ban_appeals'] = (int) (($a['open'] ?? 0) + ($a['reviewing'] ?? 0));
        }
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    try {
        $samples['topic_reports'] = $pdo->query(
            "SELECT tr.id, tr.reason, tr.created_at, t.title AS topic_title, t.id AS topic_id
             FROM topic_reports tr
             LEFT JOIN topics t ON tr.topic_id = t.id
             WHERE tr.status IN ('open','reviewing')
             ORDER BY tr.created_at DESC LIMIT 5"
        )->fetchAll() ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        $samples['ban_appeals'] = $pdo->query(
            "SELECT ba.id, ba.message, ba.created_at, u.username AS user_name
             FROM ban_appeals ba
             LEFT JOIN users u ON ba.user_id = u.id
             WHERE ba.status IN ('open','reviewing')
             ORDER BY ba.created_at DESC LIMIT 5"
        )->fetchAll() ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        $samples['pending_comments'] = $pdo->query(
            "SELECT c.id, c.body, c.created_at, u.username AS author_name, t.title AS topic_title, t.id AS topic_id
             FROM comments c
             LEFT JOIN users u ON c.user_id = u.id
             LEFT JOIN topics t ON c.topic_id = t.id
             WHERE c.status='pending' AND c.deleted_at IS NULL
             ORDER BY c.created_at DESC LIMIT 5"
        )->fetchAll() ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    try {
        $samples['user_reports'] = $pdo->query(
            "SELECT ur.id, ur.reason, ur.created_at, u.username AS reported_name
             FROM user_reports ur
             LEFT JOIN users u ON ur.reported_user_id = u.id
             WHERE ur.status IN ('open','reviewing')
             ORDER BY ur.created_at DESC LIMIT 5"
        )->fetchAll() ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

$total = array_sum($queue);
$activeQueues = count(array_filter($queue, static fn (int $count): bool => $count > 0));

$cards = [
    [
        'key' => 'topic_reports',
        'title' => 'Konu Raporları',
        'desc' => 'Açık veya incelemedeki konu şikayetleri',
        'icon' => 'bi-flag-fill',
        'tone' => 'danger',
        'href' => $baseUri . '/admin/complaints-reports.php?tab=topics&status=open',
        'cta' => 'Raporları İncele',
    ],
    [
        'key' => 'user_reports',
        'title' => 'Kullanıcı Şikayetleri',
        'desc' => 'Kullanıcılar hakkında açılan şikayetler',
        'icon' => 'bi-person-exclamation',
        'tone' => 'danger',
        'href' => $baseUri . '/admin/complaints-reports.php?tab=users&status=open',
        'cta' => 'Şikayetleri Gör',
    ],
    [
        'key' => 'ban_appeals',
        'title' => 'Ban İtirazları',
        'desc' => 'Yasaklı kullanıcıların itiraz mesajları',
        'icon' => 'bi-megaphone-fill',
        'tone' => 'warning',
        'href' => $baseUri . '/admin/users.php?tab=appeals',
        'cta' => 'İtirazları Aç',
    ],
    [
        'key' => 'pending_topics',
        'title' => 'Taslak Konular',
        'desc' => 'Yayına geçmeden önce gözden geçirilecek taslaklar',
        'icon' => 'bi-files',
        'tone' => 'warning',
        'href' => $baseUri . '/admin/topics.php?status=draft',
        'cta' => 'Konuları Aç',
    ],
    [
        'key' => 'pending_comments',
        'title' => 'Onay Bekleyen Yorumlar',
        'desc' => 'Yayınlanmadan önce gözden geçirilecek yorumlar',
        'icon' => 'bi-chat-left-text',
        'tone' => 'info',
        'href' => $baseUri . '/admin/comments-manager.php?status=pending',
        'cta' => 'Yorumları Gör',
    ],
    [
        'key' => 'unread_notifs',
        'title' => 'Okunmamış Bildirimler',
        'desc' => 'Sistem ve moderasyon bildirimleri',
        'icon' => 'bi-bell-fill',
        'tone' => 'info',
        'href' => $baseUri . '/admin/notifications.php',
        'cta' => 'Bildirimleri Aç',
    ],
];

function formatRelative(?string $ts): string
{
    if (!$ts) return '';
    $t = strtotime($ts);
    if (!$t) return htmlspecialchars($ts);
    $diff = time() - $t;
    if ($diff < 60) return $diff . ' sn önce';
    if ($diff < 3600) return floor($diff / 60) . ' dk önce';
    if ($diff < 86400) return floor($diff / 3600) . ' sa önce';
    if ($diff < 2592000) return floor($diff / 86400) . ' gün önce';
    return date('d.m.Y', $t);
}

require_once __DIR__ . '/header.php';
?>
<div class="queue-page">
    <section class="queue-hero">
        <div>
            <h2><i class="bi bi-inbox-fill"></i> Bekleyen İş Kuyruğu</h2>
            <p>Onay, inceleme veya müdahale bekleyen tüm işleri tek ekranda görün.</p>
        </div>
        <div class="queue-hero-actions">
            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-reload>
                <i class="bi bi-arrow-clockwise"></i> Yenile
            </button>
            <a href="<?= $baseUri ?>/admin/index.php" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-sm">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </section>

    <div class="ui-admin-queue-summary">
        <div class="ui-admin-queue-summary-text">
            <strong><?= number_format($total, 0, ',', '.') ?></strong>
            <span><?= $total === 0 ? 'Harika — şu an bekleyen iş yok' : 'iş bekliyor — aşağıdaki kategorileri inceleyin' ?></span>
        </div>
        <div class="ui-admin-queue-summary-icon">
            <i class="bi bi-<?= $total === 0 ? 'check2-circle' : 'hourglass-split' ?>"></i>
        </div>
    </div>

    <div class="queue-insight-strip" aria-label="Kuyruk özeti">
        <div>
            <span>Aktif kategori</span>
            <strong><?= number_format($activeQueues, 0, ',', '.') ?></strong>
        </div>
        <div>
            <span>Öncelik</span>
            <strong><?= $total === 0 ? 'Temiz' : ($queue['topic_reports'] + $queue['user_reports'] + $queue['ban_appeals'] > 0 ? 'Yüksek' : 'Normal') ?></strong>
        </div>
        <div>
            <span>Odak</span>
            <strong><?= $total === 0 ? 'İzleme' : 'İnceleme' ?></strong>
        </div>
    </div>

    <?php if ($total === 0): ?>
        <div class="ui-admin-card ui-card">
            <div class="ui-admin-empty ui-admin-empty-pro ui-admin-empty-queue ui-empty">
                <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-check2-all"></i></div>
                <h3 class="ui-admin-empty-title ui-empty">Tüm kuyruklar temiz</h3>
                <p class="ui-admin-empty-desc ui-empty">Şu an için inceleme bekleyen rapor, itiraz veya konu yok. Yeni bildirim geldiğinde burada belirir.</p>
                <div class="ui-admin-empty-meta" aria-label="Kuyruk durumu">
                    <span><i class="bi bi-inbox"></i> 0 bekleyen iş</span>
                    <span><i class="bi bi-shield-check"></i> Moderasyon sakin</span>
                </div>
                <div class="ui-admin-empty-actions ui-empty">
                    <a href="<?= $baseUri ?>/admin/index.php" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-speedometer2"></i> Dashboard'a Dön</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="ui-admin-queue-grid ui-grid">
            <?php foreach ($cards as $card): $n = (int) ($queue[$card['key']] ?? 0); $isEmpty = $n === 0; $priority = $isEmpty ? 'low' : ($card['tone'] === 'danger' ? 'high' : ($card['tone'] === 'warning' ? 'medium' : 'normal')); ?>
                <a href="<?= $card['href'] ?>" class="ui-admin-queue-card<?= $isEmpty ? ' is-empty' : '' ?> ui-card" data-priority="<?= htmlspecialchars($priority) ?>">
                    <?php if (!$isEmpty && $card['tone'] === 'danger'): ?>
                        <span class="ui-admin-queue-pulse" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="ui-admin-queue-card-head ui-panel__head ui-card">
                        <div class="ui-admin-queue-icon tone-<?= htmlspecialchars($card['tone']) ?>">
                            <i class="bi <?= htmlspecialchars($card['icon']) ?>"></i>
                        </div>
                        <span class="ui-admin-queue-count"><?= number_format($n, 0, ',', '.') ?></span>
                    </div>
                    <h3 class="ui-admin-queue-title"><?= htmlspecialchars($card['title']) ?></h3>
                    <p class="ui-admin-queue-desc"><?= htmlspecialchars($card['desc']) ?></p>
                    <?php if (!$isEmpty): ?>
                    <span class="ui-admin-priority-badge is-<?= htmlspecialchars($priority) ?>"><i class="bi bi-flag-fill"></i> <?= $priority === 'high' ? 'Yüksek öncelik' : ($priority === 'medium' ? 'Orta öncelik' : 'Normal') ?></span>
                    <?php endif; ?>
                    <span class="ui-admin-queue-cta"><?= htmlspecialchars($card['cta']) ?> <i class="bi bi-arrow-right"></i></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        $sampleBlocks = [
            'topic_reports' => ['Konu Raporları', 'bi-flag', $baseUri . '/admin/complaints-reports.php?tab=topics&status=open'],
            'ban_appeals' => ['Ban İtirazları', 'bi-megaphone', $baseUri . '/admin/users.php?tab=appeals'],
            'pending_comments' => ['Onay Bekleyen Yorumlar', 'bi-chat-left-text', $baseUri . '/admin/comments-manager.php?status=pending'],
        ];
        $hasSamples = false;
        foreach ($sampleBlocks as $sk => $sb) { if (!empty($samples[$sk])) { $hasSamples = true; break; } }
        ?>

        <?php if ($hasSamples): ?>
        <h3 class="ui-admin-queue-samples-title">Son Bekleyenler</h3>
        <div class="queue-samples">
            <?php foreach ($sampleBlocks as $sk => $sb): ?>
                <?php if (!empty($samples[$sk])): ?>
                    <div class="queue-sample-card">
                        <div class="queue-sample-head">
                            <span><i class="bi <?= $sb[1] ?>"></i> <?= htmlspecialchars($sb[0]) ?></span>
                            <a href="<?= $sb[2] ?>">Tümü <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <ul class="queue-sample-list">
                            <?php foreach ($samples[$sk] as $item): ?>
                                <li>
                                    <?php if ($sk === 'topic_reports'): ?>
                                        <a href="<?= $baseUri ?>/admin/edit.php?id=<?= (int)($item['topic_id'] ?? 0) ?>">
                                            <?= htmlspecialchars((string)($item['topic_title'] ?? 'Konu')) ?>
                                        </a>
                                        <span class="meta"><?= htmlspecialchars(mb_substr((string)($item['reason'] ?? ''), 0, 80)) ?> · <?= formatRelative($item['created_at'] ?? null) ?></span>
                                    <?php elseif ($sk === 'ban_appeals'): ?>
                                        <strong><?= htmlspecialchars((string)($item['user_name'] ?? 'Kullanıcı')) ?></strong>
                                        <span class="meta"><?= htmlspecialchars(mb_substr((string)($item['message'] ?? ''), 0, 90)) ?> · <?= formatRelative($item['created_at'] ?? null) ?></span>
                                    <?php elseif ($sk === 'pending_comments'): ?>
                                        <a href="<?= $baseUri ?>/admin/comments-manager.php#comment-<?= (int)$item['id'] ?>">
                                            <?= htmlspecialchars(mb_substr(strip_tags((string)($item['body'] ?? '')), 0, 80)) ?>
                                        </a>
                                        <span class="meta"><?= htmlspecialchars((string)($item['author_name'] ?? 'Anonim')) ?> · <?= htmlspecialchars((string)($item['topic_title'] ?? '')) ?> · <?= formatRelative($item['created_at'] ?? null) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
