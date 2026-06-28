<?php

declare(strict_types=1);

namespace App\Core\Queue;

interface Job
{
    public function handle(): void;
}
