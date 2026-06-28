<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,string> */
    private array $headers;

    /** @var array<string,mixed> */
    private array $server;

    /** @var array<string,mixed> */
    private array $cookies;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,mixed> */
    private array $attributes;

    /**
     * @param array<string,mixed> $query
     * @param array<string,string> $headers
     * @param array<string,mixed> $server
     * @param array<string,mixed> $cookies
     * @param array<string,mixed> $files
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        private string $method,
        private string $uri,
        array $query = [],
        array $headers = [],
        private string $body = '',
        array $server = [],
        array $cookies = [],
        array $files = [],
        array $attributes = [],
    ) {
        $this->query = $query;
        $this->headers = $headers;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->attributes = $attributes;
    }

    public static function fromGlobals(): self
    {
        $body = file_get_contents('php://input');
        if ($body === false) {
            $body = '';
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET ?? [],
            self::collectHeaders(),
            $body,
            $_SERVER ?? [],
            $_COOKIE ?? [],
            $_FILES ?? [],
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        $path = (string) parse_url($this->uri, PHP_URL_PATH);
        return $path !== '' ? $path : '/';
    }

    /**
     * @return array<string,mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                return $headerValue;
            }
        }

        return $default;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string,mixed>
     */
    public function getServer(): array
    {
        return $this->server;
    }

    public function serverParam(string $name, mixed $default = null): mixed
    {
        return $this->server[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return array<string,mixed>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * @return array<string,string>
     */
    private static function collectHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                foreach ($allHeaders as $name => $value) {
                    $headers[(string) $name] = (string) $value;
                }
            }
        }

        if ($headers !== []) {
            return $headers;
        }

        foreach ($_SERVER ?? [] as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }
}
