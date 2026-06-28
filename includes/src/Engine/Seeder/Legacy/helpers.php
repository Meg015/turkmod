<?php
/**
 * Seeder Module — Demo verileri DB'ye yazar
 * Sadece tablolar boşsa çalışır.
 */

declare(strict_types=1);

function seederRun(PDO $pdo): void
{
    if (!seederShouldRun($pdo)) {
        return;
    }

    seederSeedCategories($pdo);
    seederSeedTopics($pdo);
}

function seederShouldRun(PDO $pdo): bool
{
    try {
        $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $topicCount = (int)$pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();

        return $userCount > 0 && $categoryCount === 0 && $topicCount === 0;
    } catch (Throwable $e) {
        return false;
    }
}

function seederSeedCategories(PDO $pdo): void
{
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($count > 0) return;

        $categories = [
            ['name' => 'Design',     'slug' => 'design',     'description' => 'UI/UX tasarım kalıpları ve bileşenler'],
            ['name' => 'Development','slug' => 'development', 'description' => 'Yazılım geliştirme, mimari ve araçlar'],
            ['name' => 'Operations', 'slug' => 'operations', 'description' => 'Topluluk yönetimi, süreçler ve kontrol listeleri'],
        ];

        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, status, display_order, created_at, updated_at)
                                VALUES (:name, :slug, :desc, 'active', :sort, NOW(), NOW())");
        $sort = 0;
        foreach ($categories as $cat) {
            $stmt->execute(['name' => $cat['name'], 'slug' => $cat['slug'], 'desc' => $cat['description'], 'sort' => $sort++]);
        }
    } catch (Throwable $e) {
        error_log('Seeder category seed failed: ' . $e->getMessage());
    }
}

function seederSeedTopics(PDO $pdo): void
{
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM topics")->fetchColumn();
        if ($count > 0) return;

        // Kategori ID'lerini çek
        $cats = [];
        $rows = $pdo->query("SELECT id, slug FROM categories")->fetchAll();
        foreach ($rows as $r) $cats[$r['slug']] = (int)$r['id'];

        // İlk kullanıcı ID
        $authorId = (int)$pdo->query("SELECT MIN(id) FROM users")->fetchColumn();
        if ($authorId <= 0) $authorId = null;

        $topics = [];

        $stmt = $pdo->prepare("INSERT INTO topics (title, slug, category_id, author_id, topic_descriptions, status, download_count, view_count, published_at, created_at, updated_at)
                                VALUES (:title, :slug, :cat_id, :author, :desc, 'published', :dl, :views, NOW(), NOW(), NOW())");

        foreach ($topics as $t) {
            $catId = $cats[$t['category_slug']] ?? null;
            $stmt->execute([
                'title' => $t['title'],
                'slug' => $t['slug'],
                'cat_id' => $catId,
                'author' => $authorId,
                'desc' => $t['topic_descriptions'],
                'dl' => $t['download_count'],
                'views' => $t['view_count'],
            ]);
        }
    } catch (Throwable $e) {
        error_log('Seeder topic seed failed: ' . $e->getMessage());
    }
}
