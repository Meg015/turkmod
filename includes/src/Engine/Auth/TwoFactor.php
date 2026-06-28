<?php

declare(strict_types=1);

namespace App\Engine\Auth;

interface TwoFactor
{
    /**
     * Returns true when the user should be routed through a two-factor gate.
     *
     * This hook is intentionally not enforced day one.
     *
     * @param array<string,mixed> $user
     */
    public function isEnabledForUser(array $user): bool;
}
