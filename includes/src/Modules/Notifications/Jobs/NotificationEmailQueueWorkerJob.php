<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Core\Queue\Job;
use App\Modules\Notifications\Services\NotificationEmailQueueService;
use PDO;

final class NotificationEmailQueueWorkerJob implements Job
{
    /** @var callable(array<string,mixed>):bool|null */
    private $sender;

    /** @var array{selected:int,sent:int,failed:int,requeued:int,dry_run:int,errors:list<string>} */
    private array $result = [
        'selected' => 0,
        'sent' => 0,
        'failed' => 0,
        'requeued' => 0,
        'dry_run' => 0,
        'errors' => [],
    ];

    /**
     * @param callable(array<string,mixed>):bool|null $sender
     */
    public function __construct(
        private PDO $pdo,
        private NotificationEmailQueueService $emailQueue,
        private int $limit = 25,
        private bool $dryRun = false,
        ?callable $sender = null,
    ) {
        $this->sender = $sender;
    }

    public function handle(): void
    {
        $this->result = $this->emailQueue->process($this->pdo, $this->limit, $this->dryRun, $this->sender);
    }

    /**
     * @return array{selected:int,sent:int,failed:int,requeued:int,dry_run:int,errors:list<string>}
     */
    public function result(): array
    {
        return $this->result;
    }
}
