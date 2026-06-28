<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Events;

use App\Core\Events\Event;

final class TopicWorkflowEvent implements Event
{
    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        private readonly string $name,
        private readonly array $payload,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}

