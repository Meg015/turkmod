<?php

declare(strict_types=1);

namespace App\Core\Events;

interface Event
{
    public function name(): string;

    /**
     * @return array<string,mixed>
     */
    public function payload(): array;
}
