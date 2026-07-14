<?php

declare(strict_types=1);

namespace App\Engine\Media;

use PDO;

final class PdoTopicMediaGateway implements TopicMediaGateway
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
    ): array {
        if ($imageSequence === null) {
            return handleFileUpload(
                $pdo,
                $topicId,
                $file,
                $mediaType,
                $displayOrder,
                $isPrimary,
                $topicTitle,
            );
        }

        return handleFileUpload(
            $pdo,
            $topicId,
            $file,
            $mediaType,
            $displayOrder,
            $isPrimary,
            $topicTitle,
            $imageSequence,
        );
    }

    public function createRemoteVideo(PDO $pdo, int $topicId, string $videoUrl, int $displayOrder = 1): void
    {
        createTopicMediaRecord($pdo, $topicId, $videoUrl, 'video', $displayOrder, false, 'remote');
    }

    public function deletePhysicalFile(string $path): void
    {
        topicDeletePhysicalFile($path);
    }
}
