<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use PDO;
use Throwable;

final class ContactCategoryService
{
    public function __construct(
        private ?ContactSchemaService $schema = null,
    ) {
        $this->schema ??= new ContactSchemaService();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(PDO $pdo, bool $activeOnly = false): array
    {
        $this->schema->ensureCategoriesTable($pdo);

        try {
            $where = $activeOnly ? 'WHERE is_active = 1' : '';
            $stmt = $pdo->query("SELECT * FROM contact_categories {$where} ORDER BY is_active DESC, sort_order ASC, name ASC");

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            error_log('Contact category list failed: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(PDO $pdo, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $this->schema->ensureCategoriesTable($pdo);

        try {
            $stmt = $pdo->prepare('SELECT * FROM contact_categories WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findBySlug(PDO $pdo, string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $this->schema->ensureCategoriesTable($pdo);

        try {
            $stmt = $pdo->prepare('SELECT * FROM contact_categories WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,message:string,category:?array<string,mixed>,id:int,slug:string}
     */
    public function save(PDO $pdo, array $input, ?int $categoryId = null): array
    {
        $this->schema->ensureSchema($pdo);

        $name = trim((string) ($input['name'] ?? ''));
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $icon = trim((string) ($input['icon'] ?? 'bi-envelope'));
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $isActive = !empty($input['is_active']) ? 1 : 0;

        if ($name === '') {
            return ['success' => false, 'message' => 'Kategori adi zorunludur.', 'category' => null, 'id' => 0, 'slug' => ''];
        }

        if ($icon === '') {
            $icon = 'bi-envelope';
        }

        $baseSlug = $slugInput !== '' ? (function_exists('slugify') ? slugify($slugInput) : $slugInput) : (function_exists('slugify') ? slugify($name) : $name);
        $baseSlug = trim((string) $baseSlug);
        if ($baseSlug === '') {
            $baseSlug = 'contact-category';
        }

        $slug = $this->uniqueSlug($pdo, $baseSlug, $categoryId);
        $nowSql = $this->schema->nowSql($pdo);

        try {
            if ($categoryId !== null && $categoryId > 0 && $this->find($pdo, $categoryId)) {
                $sql = 'UPDATE contact_categories SET name = :name, slug = :slug, icon = :icon, sort_order = :sort_order, is_active = :is_active, updated_at = ' . $nowSql . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'icon' => $icon,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'id' => $categoryId,
                ]);
                $savedId = $categoryId;
                $message = 'Kategori guncellendi.';
            } else {
                $sql = 'INSERT INTO contact_categories (name, slug, icon, sort_order, is_active, created_at, updated_at) VALUES (:name, :slug, :icon, :sort_order, :is_active, ' . $nowSql . ', ' . $nowSql . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'icon' => $icon,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                ]);
                $savedId = (int) $pdo->lastInsertId();
                $message = 'Kategori eklendi.';
            }

            return [
                'success' => true,
                'message' => $message,
                'category' => $savedId > 0 ? $this->find($pdo, $savedId) : null,
                'id' => $savedId,
                'slug' => $slug,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Kategori kaydedilemedi.',
                'category' => null,
                'id' => 0,
                'slug' => $slug,
            ];
        }
    }

    public function delete(PDO $pdo, int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $this->schema->ensureCategoriesTable($pdo);

        try {
            $stmt = $pdo->prepare('DELETE FROM contact_categories WHERE id = ?');
            $stmt->execute([$categoryId]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function toggleActive(PDO $pdo, int $categoryId, bool $active): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $this->schema->ensureCategoriesTable($pdo);

        try {
            $stmt = $pdo->prepare('UPDATE contact_categories SET is_active = :is_active, updated_at = ' . $this->schema->nowSql($pdo) . ' WHERE id = :id');
            $stmt->execute([
                'is_active' => $active ? 1 : 0,
                'id' => $categoryId,
            ]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function uniqueSlug(PDO $pdo, string $baseSlug, ?int $ignoreId = null): string
    {
        $baseSlug = trim($baseSlug, '-');
        if ($baseSlug === '') {
            $baseSlug = 'contact-category';
        }

        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($pdo, $candidate, $ignoreId)) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(PDO $pdo, string $slug, ?int $ignoreId = null): bool
    {
        try {
            if ($ignoreId !== null && $ignoreId > 0) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_categories WHERE slug = ? AND id <> ?');
                $stmt->execute([$slug, $ignoreId]);
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_categories WHERE slug = ?');
                $stmt->execute([$slug]);
            }

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }
}
