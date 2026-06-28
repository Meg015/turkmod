<?php

declare(strict_types=1);

namespace App\Core\Http;

class Response
{
    /** @var array<string,string> */
    private array $headers;

    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        array $headers = [],
    ) {
        $this->headers = $headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                return $headerValue;
            }
        }

        return $default;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        foreach (array_keys($clone->headers) as $existingName) {
            if (strcasecmp($existingName, $name) === 0) {
                unset($clone->headers[$existingName]);
            }
        }
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withStatusCode(int $statusCode): static
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;

        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }

    public function __toString(): string
    {
        return $this->body;
    }
}
