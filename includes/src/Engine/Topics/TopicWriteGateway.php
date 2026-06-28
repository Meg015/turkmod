<?php

declare(strict_types=1);

namespace App\Engine\Topics;

use PDO;

interface TopicWriteGateway
{
    public function generateSlug(PDO $pdo, string $title): string;

    /**
     * @param array<string,mixed> $topicData
     */
    public function createTopic(PDO $pdo, array $topicData): int;

    public function syncDownloadLinks(PDO $pdo, int $topicId, string $downloadLines): void;

    public function setPrimaryMediaId(PDO $pdo, int $topicId, ?int $primaryMediaId): void;
}

