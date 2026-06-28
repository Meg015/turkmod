<?php

declare(strict_types=1);

namespace App\Engine\Auth;

final class DisabledTwoFactor implements TwoFactor
{
    /**
     * @param array<string,mixed> $user
     */
    public function isEnabledForUser(array $user): bool
    {
        return false;
    }
}
