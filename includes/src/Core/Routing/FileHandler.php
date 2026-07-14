<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;
use InvalidArgumentException;

final class FileHandler implements Handler
{
    public function __construct(private string $filePath)
    {
        if ($this->filePath === '') {
            throw new InvalidArgumentException('File handler requires a file path.');
        }
    }

    public static function for(string $filePath): self
    {
        return new self($filePath);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function handle(Request $request): Response
    {
        if (!is_file($this->filePath)) {
            throw new InvalidArgumentException('File handler target does not exist: ' . $this->filePath);
        }

        ob_start();
        include $this->filePath;
        $body = (string) ob_get_clean();

        return new Response($body, http_response_code() ?: 200);
    }
}
