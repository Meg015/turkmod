<?php

declare(strict_types=1);

namespace App\Core\Http;

use InvalidArgumentException;
use RuntimeException;

final class FileResponse extends Response
{
    private string $filePath;

    private ?string $downloadName;

    private string $mimeType;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        string $filePath,
        ?string $downloadName = null,
        ?string $mimeType = null,
        int $statusCode = 200,
        array $headers = [],
    ) {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('File response path is not readable: ' . $filePath);
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException('File response could not read: ' . $filePath);
        }

        $this->filePath = $filePath;
        $this->downloadName = $downloadName !== null && $downloadName !== '' ? $downloadName : null;
        $this->mimeType = $mimeType !== null && $mimeType !== '' ? $mimeType : self::guessMimeType($filePath);

        $responseHeaders = array_merge([
            'Content-Type' => $this->mimeType,
            'Content-Length' => (string) strlen($contents),
        ], $headers);

        if ($this->downloadName !== null) {
            $responseHeaders['Content-Disposition'] = 'attachment; filename="' . addcslashes($this->downloadName, "\"\\") . '"';
        }

        $modifiedAt = @filemtime($filePath);
        if ($modifiedAt !== false) {
            $responseHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT';
        }

        parent::__construct($contents, $statusCode, $responseHeaders);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getDownloadName(): ?string
    {
        return $this->downloadName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function isDownload(): bool
    {
        return $this->downloadName !== null;
    }

    private static function guessMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html', 'htm' => 'text/html',
            default => 'application/octet-stream',
        };
    }
}
