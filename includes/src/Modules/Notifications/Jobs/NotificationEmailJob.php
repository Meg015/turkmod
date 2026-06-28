<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Core\Queue\Job;
use App\Modules\Notifications\Services\NotificationEmailQueueService;
use PDO;
use Throwable;

final class NotificationEmailJob implements Job
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        private PDO $pdo,
        private NotificationEmailQueueService $emailQueue,
        private int $notificationId,
        private int $recipientId,
        private string $templateKey,
        private string $subject,
        private string $body,
        private ?string $link,
        private array $metadata,
        private int $maxAttempts = 3,
        private bool $tracksDeliveryChannels = true,
    ) {
    }

    public function handle(): void
    {
        $emailQueued = false;

        try {
            $emailQueued = $this->emailQueue->queue(
                $this->pdo,
                $this->notificationId,
                $this->recipientId,
                $this->templateKey,
                $this->subject,
                $this->body,
                $this->link,
                $this->metadata,
                $this->maxAttempts
            );
        } catch (Throwable $e) {
            error_log('NotificationEmailJob failed to queue email: ' . $e->getMessage());
        }

        $this->markDeliveryChannels($emailQueued);
    }

    private function markDeliveryChannels(bool $emailQueued): void
    {
        if (!$this->tracksDeliveryChannels || $this->notificationId <= 0) {
            return;
        }

        try {
            $deliveryChannels = ['in_app', $emailQueued ? 'email_queue' : 'email_queue_failed'];
            $update = $this->pdo->prepare('UPDATE notifications SET delivery_channels = ? WHERE id = ?');
            $update->execute([json_encode($deliveryChannels, JSON_UNESCAPED_UNICODE), $this->notificationId]);
        } catch (Throwable $e) {
            error_log('NotificationEmailJob delivery channel update failed: ' . $e->getMessage());
        }
    }
}
