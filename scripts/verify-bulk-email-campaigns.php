<?php

declare(strict_types=1);

use App\Modules\Notifications\Services\BulkEmailCampaignService;
use App\Modules\Notifications\Services\BulkEmailContentService;
use App\Modules\Notifications\Services\BulkEmailWorkerService;

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$content = new BulkEmailContentService();
$campaigns = new BulkEmailCampaignService($content);
$worker = new BulkEmailWorkerService($campaigns, $content);
$campaignId = 0;

try {
    $clean = $content->validateAndSanitize(
        'Duyuru {{site_name}}',
        '<p onclick="alert(1)">Merhaba <strong>{{username}}</strong></p><script>alert(1)</script>'
    );
    $assert(!str_contains($clean['body_html'], 'onclick'), 'Event attribute was not removed.');
    $assert(!str_contains($clean['body_html'], '<script'), 'Script element was not removed.');

    $embedRejected = false;
    try {
        $content->validateAndSanitize('Video', '<p>Video</p><iframe src="https://example.com"></iframe>');
    } catch (RuntimeException) {
        $embedRejected = true;
    }
    $assert($embedRejected, 'Embedded media must be rejected.');

    $rendered = $content->render('Merhaba {{username}}', '<p>{{email}}</p>', $content->samplePayload());
    $assert(str_contains($rendered['html'], 'data-app-mail-layout="1"'), 'Standard mail layout was not used.');
    $assert(str_contains($rendered['subject'], 'Ornek Uye'), 'Personalization was not rendered.');

    $campaigns->requireSchema($pdo);
    if ($campaigns->activeCampaign($pdo) !== null) {
        echo "Content checks passed; queue lifecycle skipped because an active campaign exists.\n";
        exit(0);
    }

    $campaignId = $campaigns->saveDraft(
        $pdo,
        0,
        'Dogrulama Kampanyasi {{site_name}}',
        '<p>Merhaba <strong>{{username}}</strong>, bu bir gonderimsiz dogrulama kaydidir.</p>'
    );
    $campaigns->beginPreparation($pdo, $campaignId);
    for ($i = 0; $i < 100; $i++) {
        $chunk = $campaigns->prepareRecipientChunk($pdo, $campaignId, 1000);
        if ($chunk['complete']) {
            break;
        }
    }
    $progress = $campaigns->progress($pdo, $campaignId);
    $assert((string) $progress['status'] === 'queued', 'Campaign did not enter queued state.');
    $assert((int) $progress['recipient_total'] > 3, 'Recipient snapshot is unexpectedly small.');

    $campaigns->pause($pdo, $campaignId);
    $resumed = $campaigns->resume($pdo, $campaignId);
    $assert((string) $resumed['status'] === 'queued', 'Paused campaign did not resume to queue.');

    $sent = $worker->process($pdo, 2, 3, false, static fn (): bool => true);
    $assert($sent['campaign_id'] === $campaignId && $sent['sent'] === 2, 'Injected success deliveries were not recorded.');

    $failed = $worker->process($pdo, 1, 1, false, static fn (): bool => false);
    $assert($failed['failed'] === 1, 'Terminal failure was not recorded.');
    $afterFailure = $campaigns->progress($pdo, $campaignId);
    $assert((int) $afterFailure['failed_count'] === 1, 'Failure counter is inconsistent.');

    $campaigns->retryFailed($pdo, $campaignId);
    $retried = $worker->process($pdo, 1, 3, false, static fn (): bool => true);
    $assert($retried['sent'] === 1, 'Manual retry did not return the recipient to delivery.');
    $afterRetry = $campaigns->progress($pdo, $campaignId);
    $assert((int) $afterRetry['failed_count'] === 0, 'Failure counter did not reconcile after retry.');

    $cancelled = $campaigns->cancel($pdo, $campaignId);
    $assert((string) $cancelled['status'] === 'cancelled', 'Campaign cancellation failed.');
    $assert((int) $cancelled['cancelled_count'] > 0, 'Unsent recipients were not cancelled.');

    echo "Bulk email content, snapshot, worker, retry, and cancellation checks passed.\n";
} finally {
    if ($campaignId > 0) {
        $stmt = $pdo->prepare('DELETE FROM bulk_email_campaigns WHERE id = ?');
        $stmt->execute([$campaignId]);
    }
}
