<?php

declare(strict_types=1);

namespace App\Engine\Media;

use PDO;

interface TopicMediaGateway
{
    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    public function upload(
        PDO $pdo,
        int $topicId,
        array $file,
        string $mediaType,
        int $displayOrder,
        bool $isPrimary,
        string $topicTitle,
        ?int $imageSequence = null,
    ): array;

    public function createRemoteVideo(PDO $pdo, int $topicId, string $videoUrl, int $displayOrder = 1): void;

    public function deletePhysicalFile(string $path): void;
}

