<?php

declare(strict_types=1);

use App\Modules\Notifications\Services\BulkEmailCampaignService;
use App\Modules\Notifications\Services\BulkEmailContentService;

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/notifications.php';

$connection = requireDatabaseConnection($pdo ?? null);
$currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$canView = $currentUserId > 0 && function_exists('userHasPermission') && userHasPermission($connection, $currentUserId, 'notifications.view');
if (!$canView) {
    sendForbidden('Toplu e-posta kampanyalarini goruntuleme yetkiniz yok.');
}

$content = new BulkEmailContentService();
$campaigns = new BulkEmailCampaignService($content);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        $campaigns->requireSchema($connection);
        $campaignId = max(0, (int) ($_GET['campaign_id'] ?? 0));
        if ($campaignId > 0) {
            sendSuccess('Kampanya durumu yuklendi.', ['campaign' => $campaigns->progress($connection, $campaignId)]);
        }
        sendSuccess('Toplu e-posta merkezi yuklendi.', [
            'active_campaign' => $campaigns->activeCampaign($connection),
            'history' => $campaigns->history($connection, 20),
            'eligible_recipient_count' => $campaigns->eligibleRecipientCount($connection),
        ]);
    } catch (Throwable $e) {
        sendServerError('Toplu e-posta bilgileri yuklenemedi.', $e);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['GET', 'POST']);
}
if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
    sendCsrfError();
}

$action = trim((string) ($_POST['action'] ?? ''));
$manageActions = ['preview', 'test', 'save'];
$dispatchActions = ['start', 'pause', 'resume', 'cancel', 'retry'];
$requiredPermission = in_array($action, $dispatchActions, true) ? 'notifications.dispatch' : 'notifications.manage';
if (!in_array($action, array_merge($manageActions, $dispatchActions), true)) {
    sendValidationError('Gecersiz toplu e-posta islemi.');
}
if (!userHasPermission($connection, $currentUserId, $requiredPermission)) {
    sendForbidden('Bu toplu e-posta islemi icin yetkiniz yok.');
}

$audit = static function (string $type, int $campaignId, array $data = []) use ($connection): void {
    if (function_exists('logActivity')) {
        logActivity($connection, $type, 'bulk_email_campaign', $campaignId > 0 ? $campaignId : null, $data);
    }
    if (function_exists('adminAuditLogger')) {
        adminAuditLogger()->logAction($connection, $type, 'bulk_email_campaign', $campaignId, 'Toplu e-posta kampanya islemi', [], $data, false);
    }
};

try {
    $campaigns->requireSchema($connection);
    if ($action === 'preview') {
        $message = $content->render(
            (string) ($_POST['subject'] ?? ''),
            (string) ($_POST['body_html'] ?? ''),
            $content->samplePayload()
        );
        sendSuccess('E-posta onizlemesi hazirlandi.', [
            'preview' => ['subject' => $message['subject'], 'html' => $message['html']],
        ]);
    }

    if ($action === 'test') {
        $recipient = trim((string) ($_POST['test_email'] ?? ''));
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            sendValidationError('Gecerli bir test e-posta adresi girin.');
        }
        $userStmt = $connection->prepare('SELECT id, username, email FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$currentUserId]);
        $adminUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $ok = $content->send(
            $recipient,
            (string) ($_POST['subject'] ?? ''),
            (string) ($_POST['body_html'] ?? ''),
            $content->samplePayload($adminUser),
            [
                'source' => 'bulk_email_test',
                'source_key' => 'admin:' . $currentUserId,
                'user_id' => $currentUserId,
                'recipient_name' => (string) ($adminUser['username'] ?? 'Admin'),
            ]
        );
        if (!$ok) {
            $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
            $detail = trim((string) ($mailResult['error'] ?? $mailResult['smtp_response'] ?? ''));
            throw new RuntimeException('Test e-postasi gonderilemedi.' . ($detail !== '' ? ' ' . mb_substr($detail, 0, 500, 'UTF-8') : ''));
        }
        $audit('bulk_email_test_sent', 0, ['recipient' => $recipient]);
        sendSuccess('Test e-postasi gonderildi: ' . $recipient);
    }

    if ($action === 'save' || $action === 'start') {
        $campaignId = $campaigns->saveDraft(
            $connection,
            $currentUserId,
            (string) ($_POST['subject'] ?? ''),
            (string) ($_POST['body_html'] ?? ''),
            max(0, (int) ($_POST['campaign_id'] ?? 0))
        );
        if ($action === 'save') {
            $audit('bulk_email_draft_saved', $campaignId);
            sendSuccess('Toplu e-posta taslagi kaydedildi.', ['campaign' => $campaigns->progress($connection, $campaignId)]);
        }
        $campaigns->beginPreparation($connection, $campaignId);
        $campaigns->prepareRecipientChunk($connection, $campaignId, 1000);
        $audit('bulk_email_campaign_started', $campaignId, ['eligible_count' => $campaigns->eligibleRecipientCount($connection)]);
        sendSuccess('Kampanya kuyruğa alindi. Sayfayi kapatsaniz da gonderim devam eder.', ['campaign' => $campaigns->progress($connection, $campaignId)]);
    }

    $campaignId = (int) ($_POST['campaign_id'] ?? 0);
    if ($campaignId <= 0) {
        sendValidationError('Gecerli bir kampanya secin.');
    }
    $campaign = match ($action) {
        'pause' => $campaigns->pause($connection, $campaignId),
        'resume' => $campaigns->resume($connection, $campaignId),
        'cancel' => $campaigns->cancel($connection, $campaignId),
        'retry' => $campaigns->retryFailed($connection, $campaignId),
        default => throw new RuntimeException('Gecersiz kampanya islemi.'),
    };
    $audit('bulk_email_campaign_' . $action, $campaignId, ['status' => (string) ($campaign['status'] ?? '')]);
    $messages = [
        'pause' => 'Kampanya duraklatildi.',
        'resume' => 'Kampanya kaldigi yerden devam edecek.',
        'cancel' => 'Gonderilmemis alicilar iptal edildi.',
        'retry' => 'Basarisiz alicilar yeniden siraya alindi.',
    ];
    sendSuccess($messages[$action], ['campaign' => $campaign]);
} catch (RuntimeException $e) {
    sendValidationError($e->getMessage());
} catch (Throwable $e) {
    sendServerError('Toplu e-posta islemi tamamlanamadi.', $e);
}
