<?php

declare(strict_types=1);

namespace App\Core\Events;

interface EventDispatcher
{
    public function dispatch(Event $event): Event;
}

