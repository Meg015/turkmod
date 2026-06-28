<?php

declare(strict_types=1);

namespace App\Core\Queue;

interface Queue
{
    public function push(Job $job): void;

    public function later(int $delaySeconds, Job $job): void;
}
