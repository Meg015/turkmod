<?php

declare(strict_types=1);

namespace App\Core\Routing;

final class AssetRouteAdapter
{
    /** @var array<string,string> */
    private const DEFAULT_MIME_TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
    ];

    /** @var array<string,string> */
    private array $mimeTypes;

    /**
     * @param array<string,string> $mimeTypes
     */
    public function __construct(
        array $mimeTypes = [],
        private string $cacheControl = 'public, max-age=3600',
    ) {
        $this->mimeTypes = array_merge(self::DEFAULT_MIME_TYPES, $mimeTypes);
    }

    /**
     * @param array<int,string> $assetSegments
     * @return array{path:string,mime_type:string,headers:array<string,string>}|null
     */
    public function resolve(string $assetRoot, array $assetSegments): ?array
    {
        $assetRootReal = realpath($assetRoot);
        $assetPath = $this->pathForSegments($assetRoot, $assetSegments);
        $assetReal = $assetPath !== null ? realpath($assetPath) : false;

        if (
            $assetRootReal === false ||
            $assetReal === false ||
            !$this->isInsideRoot($assetRootReal, $assetReal) ||
            !is_file($assetReal) ||
            !is_readable($assetReal)
        ) {
            return null;
        }

        $mimeType = $this->mimeTypeFor($assetReal);

        return [
            'path' => $assetReal,
            'mime_type' => $mimeType,
            'headers' => [
                'Content-Type' => $mimeType,
                'Cache-Control' => $this->cacheControl,
            ],
        ];
    }

    /**
     * @param array<int,string> $assetSegments
     */
    public function sendFromSegments(string $assetRoot, array $assetSegments): void
    {
        $asset = $this->resolve($assetRoot, $assetSegments);
        if ($asset === null) {
            http_response_code(404);

            return;
        }

        $lastModified = filemtime($asset['path']);
        $etag = sprintf('"%x-%x"', $lastModified, filesize($asset['path']));

        $asset['headers']['ETag'] = $etag;
        $asset['headers']['Last-Modified'] = gmdate('D, d M Y H:i:s', $lastModified) . ' GMT';

        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';

        if (
            ($ifNoneMatch !== '' && $ifNoneMatch === $etag) ||
            ($ifModifiedSince !== '' && strtotime($ifModifiedSince) === $lastModified)
        ) {
            foreach ($asset['headers'] as $name => $value) {
                header($name . ': ' . $value, true);
            }
            http_response_code(304);
            exit;
        }

        foreach ($asset['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }

        readfile($asset['path']);
    }

    /**
     * @param array<int,string> $assetSegments
     */
    private function pathForSegments(string $assetRoot, array $assetSegments): ?string
    {
        if ($assetSegments === []) {
            return null;
        }

        $cleanSegments = [];
        foreach ($assetSegments as $segment) {
            $segment = trim((string) $segment);
            if (
                $segment === '' ||
                $segment === '.' ||
                $segment === '..' ||
                str_contains($segment, "\0") ||
                str_contains($segment, '/') ||
                str_contains($segment, '\\')
            ) {
                return null;
            }

            $cleanSegments[] = $segment;
        }

        return rtrim($assetRoot, '/\\') . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanSegments);
    }

    private function isInsideRoot(string $assetRootReal, string $assetReal): bool
    {
        $assetRootReal = rtrim($assetRootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($assetReal, $assetRootReal);
    }

    private function mimeTypeFor(string $assetReal): string
    {
        $extension = strtolower(pathinfo($assetReal, PATHINFO_EXTENSION));

        return $this->mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
