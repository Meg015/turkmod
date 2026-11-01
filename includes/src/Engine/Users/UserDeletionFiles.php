<?php

declare(strict_types=1);

namespace App\Engine\Users;

use PDO;
use RuntimeException;

final class UserDeletionFiles
{
    /** @param list<string> $paths */
    public static function cleanup(PDO $pdo, array $paths, string $baseUri = ''): void
    {
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Dosya temizliği yalnızca commit sonrasında yapılabilir.');
        }
        $root = dirname(__DIR__, 4);
        $uploads = realpath($root . '/uploads');
        if ($uploads === false) { return; }
        $references = [];
        foreach ([['media_files', 'path', false], ['comment_media', 'file_path', false], ['users', 'avatar', false], ['topics', 'topic_descriptions', true], ['comments', 'body', true], ['static_pages', 'body_html', true], ['static_pages', 'og_image', false], ['topic_revisions', 'topic_descriptions', true]] as [$table, $column, $embedded]) {
            $exists = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? in_array($column, array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name'), true)
                : false;
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                $find = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $find->execute([$table, $column]);
                $exists = (bool) $find->fetchColumn();
            }
            if ($exists) { $references[] = [$table, $column, $embedded]; }
        }
        foreach (array_unique($paths) as $path) {
            $relative = self::relativePath($path, $baseUri);
            if ($relative === null) { continue; }
            $candidate = $root . '/' . $relative;
            $real = realpath($candidate);
            if ($real === false || !is_file($real) || is_link($candidate) || !self::withinUploads($real, $uploads)) { continue; }
            if (self::isReferenced($pdo, $references, $path, $relative)) { continue; }
            if (!unlink($real)) { throw new RuntimeException('Silinen hesaba ait bir dosya temizlenemedi.'); }
            $thumbPath = dirname($candidate) . '/thumbs/' . basename($candidate);
            $thumb = realpath($thumbPath);
            $thumbRelative = dirname($relative) . '/thumbs/' . basename($relative);
            if ($thumb !== false && is_file($thumb) && !is_link($thumbPath) && self::withinUploads($thumb, $uploads) && !self::isReferenced($pdo, $references, $thumbRelative, $thumbRelative) && !unlink($thumb)) {
                throw new RuntimeException('Silinen dosyanın küçük görseli temizlenemedi.');
            }
        }
    }

    private static function relativePath(string $path, string $baseUri): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, "\0") || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $path)) { return null; }
        $base = '/' . trim(str_replace('\\', '/', $baseUri), '/') . '/';
        if ($base !== '//' && str_starts_with($path, $base)) { $path = substr($path, strlen($base)); }
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'uploads/') || array_intersect(explode('/', $path), ['.', '..'])) { return null; }
        return $path;
    }

    private static function withinUploads(string $path, string $uploads): bool
    {
        return str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $uploads), '/') . '/');
    }

    /** @param list<array{string,string,bool}> $references */
    private static function isReferenced(PDO $pdo, array $references, string $path, string $relative): bool
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $relative);
        $suffix = '%/' . $escaped;
        foreach ($references as [$table, $column, $embedded]) {
            $normalized = "REPLACE({$column}, CHAR(92), '/')";
            if ($embedded) {
                $find = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$normalized} LIKE ? ESCAPE '!' LIMIT 1");
                $find->execute(['%' . $escaped . '%']);
            } else {
                $find = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = ? OR {$normalized} = ? OR {$normalized} LIKE ? ESCAPE '!' OR {$normalized} LIKE ? ESCAPE '!' OR {$normalized} LIKE ? ESCAPE '!' OR {$normalized} LIKE ? ESCAPE '!' OR {$normalized} LIKE ? ESCAPE '!' LIMIT 1");
                $find->execute([$path, $relative, $suffix, $suffix . '?%', $suffix . '#%', $escaped . '?%', $escaped . '#%']);
            }
            if ($find->fetchColumn()) { return true; }
        }
        return false;
    }
}
