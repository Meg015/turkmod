<?php

declare(strict_types=1);

namespace App\Core\Http;

final class JsonResponse extends Response
{
    private mixed $payload;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(mixed $payload, int $statusCode = 200, array $headers = [])
    {
        $this->payload = $payload;

        parent::__construct(
            $this->encode($payload),
            $statusCode,
            array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers),
        );
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    private function encode(mixed $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
