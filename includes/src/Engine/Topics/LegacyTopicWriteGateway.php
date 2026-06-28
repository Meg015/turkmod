<?php

declare(strict_types=1);

namespace App\Engine\Topics;

use PDO;

final class LegacyTopicWriteGateway implements TopicWriteGateway
{
    public function generateSlug(PDO $pdo, string $title): string
    {
        return generateUniqueSlug($pdo, $title, 'topics');
    }

    /**
     * @param array<string,mixed> $topicData
     */
    public function createTopic(PDO $pdo, array $topicData): int
    {
        $categoryId = (int) ($topicData['category_id'] ?? 0);
        $authorId = (int) ($topicData['author_id'] ?? 0);
        $title = trim((string) ($topicData['title'] ?? ''));
        $slug = trim((string) ($topicData['slug'] ?? ''));
        $authorTopic = trim((string) ($topicData['author_topic'] ?? ''));
        $topicVersion = trim((string) ($topicData['topic_version'] ?? ''));
        $content = (string) ($topicData['content'] ?? '');
        $status = (string) ($topicData['status'] ?? 'published');
        $moderationFlagsJson = $topicData['moderation_flags_json'] ?? null;

        $pdo->prepare(
            'INSERT INTO topics (category_id, author_id, title, slug, author_topic, topic_version, topic_descriptions, status, moderation_flags, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        )->execute([
            $categoryId,
            $authorId,
            $title,
            $slug,
            $authorTopic !== '' ? $authorTopic : null,
            $topicVersion !== '' ? $topicVersion : null,
            $content,
            $status,
            is_string($moderationFlagsJson) ? $moderationFlagsJson : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function syncDownloadLinks(PDO $pdo, int $topicId, string $downloadLines): void
    {
        syncTopicDownloadLinks($pdo, $topicId, $downloadLines);
    }

    public function setPrimaryMediaId(PDO $pdo, int $topicId, ?int $primaryMediaId): void
    {
        $pdo->prepare('UPDATE topics SET primary_media_file_id = ? WHERE id = ?')
            ->execute([$primaryMediaId, $topicId]);
    }
}

