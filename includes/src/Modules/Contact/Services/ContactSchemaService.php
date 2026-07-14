<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use App\Core\Database\SchemaInspector;
use PDO;

final class ContactSchemaService
{
    public function __construct(private ?SchemaInspector $inspector = null) { $this->inspector ??= new SchemaInspector(); }
    public function isSqlite(PDO $pdo): bool { return $this->inspector->isSqlite($pdo); }
    public function nowSql(PDO $pdo): string { return $this->isSqlite($pdo) ? "datetime('now')" : 'NOW()'; }
    public function tableExists(PDO $pdo, string $table): bool { return $this->inspector->tableExists($pdo, $table); }
    public function columnExists(PDO $pdo, string $table, string $column): bool { return $this->inspector->columnExists($pdo, $table, $column); }
    public function indexExists(PDO $pdo, string $table, string $index): bool { return $this->inspector->indexExists($pdo, $table, $index); }

    public function ensureSchema(PDO $pdo, bool $unused = true): void
    {
        $this->ensureCategoriesTable($pdo);
        $this->ensureMessagesTable($pdo);
        $this->seedDefaultCategories($pdo);
    }

    public function ensureCategoriesTable(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['contact_categories']); }

    public function ensureMessagesTable(PDO $pdo, bool $unused = true): void
    {
        $this->inspector->requireTables($pdo, ['contact_messages']);
        $this->inspector->requireColumns($pdo, 'contact_messages', ['admin_reply_admin_id']);
    }

    /** @return array<int,array{name:string,slug:string,icon:string,sort_order:int,is_active:int}> */
    public function defaultCategories(): array
    {
        return [
            ['name' => 'Destek', 'slug' => 'destek', 'icon' => 'bi-headset', 'sort_order' => 10, 'is_active' => 1],
            ['name' => 'Reklam', 'slug' => 'reklam', 'icon' => 'bi-megaphone', 'sort_order' => 20, 'is_active' => 1],
            ['name' => 'Oneri', 'slug' => 'oneri', 'icon' => 'bi-lightbulb', 'sort_order' => 30, 'is_active' => 1],
            ['name' => 'Sikayet', 'slug' => 'sikayet', 'icon' => 'bi-exclamation-triangle', 'sort_order' => 40, 'is_active' => 1],
            ['name' => 'DMCA & Telif', 'slug' => 'dmca-telif', 'icon' => 'bi-shield-lock', 'sort_order' => 50, 'is_active' => 1],
        ];
    }

    private function seedDefaultCategories(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM contact_categories')->fetchColumn() > 0) {
            return;
        }
        $now = $this->nowSql($pdo);
        $stmt = $pdo->prepare('INSERT INTO contact_categories (name, slug, icon, sort_order, is_active, created_at, updated_at) VALUES (:name, :slug, :icon, :sort_order, :is_active, ' . $now . ', ' . $now . ')');
        foreach ($this->defaultCategories() as $category) {
            $stmt->execute($category);
        }
    }
}
