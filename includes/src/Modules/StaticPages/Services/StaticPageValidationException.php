<?php

declare(strict_types=1);

namespace App\Modules\StaticPages\Services;

use RuntimeException;

final class StaticPageValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct((string) (reset($errors) ?: 'Sabit sayfa doğrulanamadı.'));
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
