<?php

declare(strict_types=1);

namespace App\Core\Events;

interface Listener
{
    public function handle(Event $event): void;
}
