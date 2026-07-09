<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Services;

use App\Core\Events\EventDispatcher;
use App\Engine\Media\LegacyTopicMediaGateway;
use App\Engine\Media\TopicMediaGateway;
use App\Engine\Topics\LegacyTopicWriteGateway;
use App\Engine\Topics\TopicWriteGateway;
use App\Modules\TopicWorkflow\Events\TopicWorkflowEvent;
use PDO;
use RuntimeException;
use Throwable;

final class TopicSubmissionService
{
    public function __construct(
        private readonly ?EventDispatcher $events = null,
        private readonly string $projectRoot = '',
        private readonly ?TopicWriteGateway $topics = null,
        private readonly ?TopicMediaGateway $media = null,
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     * @return array{topic_id:int,slug:string,status:string,category_id:int}
     */
    public function submit(PDO $pdo, array $input, array $files = []): array
    {
        $topics = $this->topics ?? new LegacyTopicWriteGateway();
        $media = $this->media ?? new LegacyTopicMediaGateway();
        $categoryId = (int) ($input['category_id'] ?? 0);
        $authorId = (int) ($input['author_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $authorTopic = trim((string) ($input['author_topic'] ?? ''));
        $topicVersion = trim((string) ($input['topic_version'] ?? ''));
        $content = (string) ($input['content'] ?? '');
        if ($content !== '') {
            $content = \sanitizeHtml($content);
        }
        $status = (string) ($input['status'] ?? 'published');
        $moderationFlagsJson = $input['moderation_flags_json'] ?? null;
        $topicDownloadLinks = trim((string) ($input['topic_download_links'] ?? ''));
        $videoUrl = trim((string) ($input['video_url'] ?? ''));
        $maxImages = max(1, (int) ($input['max_images'] ?? 10));

        $attachmentFile = $this->arrayOrNull($files['attachment'] ?? null);
        $coverFile = $this->arrayOrNull($files['cover'] ?? null);
        $galleryFiles = $this->arrayOrNull($files['gallery'] ?? null);

        $slug = $topics->generateSlug($pdo, $title);

        try {
            $uploadedPaths = [];
            $pdo->beginTransaction();

            $topicId = $topics->createTopic($pdo, [
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'title' => $title,
                'slug' => $slug,
                'author_topic' => $authorTopic,
                'topic_version' => $topicVersion,
                'content' => $content,
                'status' => $status,
                'moderation_flags_json' => $moderationFlagsJson,
            ]);

            $topics->syncDownloadLinks($pdo, $topicId, $topicDownloadLinks);

            if ($this->isUploadedFile($attachmentFile)) {
                $attachment = $media->upload(
                    $pdo,
                    $topicId,
                    $attachmentFile,
                    'attachment',
                    0,
                    false,
                    $title,
                    null,
                );
                if (isset($attachment['error'])) {
                    throw new RuntimeException((string) $attachment['error']);
                }
                if (isset($attachment['path'])) {
                    $uploadedPaths[] = (string) $attachment['path'];
                }
            }

            $primaryMediaId = null;
            $imageSequence = 1;
            if ($this->isUploadedFile($coverFile)) {
                $cover = $media->upload(
                    $pdo,
                    $topicId,
                    $coverFile,
                    'image',
                    0,
                    true,
                    $title,
                    $imageSequence++,
                );
                if (isset($cover['error'])) {
                    throw new RuntimeException((string) $cover['error']);
                }
                if (isset($cover['path'])) {
                    $uploadedPaths[] = (string) $cover['path'];
                }
                if (isset($cover['id'])) {
                    $primaryMediaId = (int) $cover['id'];
                }
            }

            $displayOrder = 1;
            if ($videoUrl !== '') {
                $media->createRemoteVideo($pdo, $topicId, $videoUrl, $displayOrder++);
            }

            $fileCount = $this->galleryFileCount($galleryFiles);
            $maxFiles = min($maxImages, $fileCount);
            for ($i = 0; $i < $maxFiles; $i++) {
                $singleFile = $this->galleryFileAt($galleryFiles, $i);
                if (!$this->isUploadedFile($singleFile)) {
                    continue;
                }

                $result = $media->upload(
                    $pdo,
                    $topicId,
                    $singleFile,
                    'image',
                    $i + 1,
                    false,
                    $title,
                    $imageSequence++,
                );
                if (isset($result['error'])) {
                    throw new RuntimeException((string) $result['error']);
                }
                if (isset($result['path'])) {
                    $uploadedPaths[] = (string) $result['path'];
                    $displayOrder++;
                }
            }

            $topics->setPrimaryMediaId($pdo, $topicId, $primaryMediaId);

            $pdo->commit();

            $this->emitCreatedEvent($pdo, $topicId, $authorId, $status, $categoryId, $slug, $title);
            $this->emitPublishedEventIfNeeded($pdo, $topicId, $authorId, $status, $categoryId, $slug, $title);

            return [
                'topic_id' => $topicId,
                'slug' => $slug,
                'status' => $status,
                'category_id' => $categoryId,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach (($uploadedPaths ?? []) as $uploadedPath) {
                $media->deletePhysicalFile((string) $uploadedPath);
            }

            throw $exception;
        }
    }

    private function emitPublishedEventIfNeeded(
        PDO $pdo,
        int $topicId,
        int $authorId,
        string $status,
        int $categoryId,
        string $slug,
        string $title,
    ): void {
        if (!in_array($status, ['published', 'approved'], true) || !$this->events instanceof EventDispatcher) {
            return;
        }

        $this->events->dispatch(new TopicWorkflowEvent('topic.published', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'status' => $status,
            'category_id' => $categoryId,
            'slug' => $slug,
            'title' => $title,
            'source' => 'topic_workflow.create',
            'pdo' => $pdo,
            'project_root' => $this->projectRoot,
        ]));
    }

    private function emitCreatedEvent(
        PDO $pdo,
        int $topicId,
        int $authorId,
        string $status,
        int $categoryId,
        string $slug,
        string $title,
    ): void {
        if (!$this->events instanceof EventDispatcher) {
            return;
        }

        $this->events->dispatch(new TopicWorkflowEvent('topic.created', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'status' => $status,
            'category_id' => $categoryId,
            'slug' => $slug,
            'title' => $title,
            'source' => 'topic_workflow.create',
            'pdo' => $pdo,
            'project_root' => $this->projectRoot,
        ]));
    }

    /**
     * @param mixed $value
     * @return array<string,mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string,mixed>|null $file
     */
    private function isUploadedFile(?array $file): bool
    {
        if (!is_array($file)) {
            return false;
        }

        return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /**
     * @param array<string,mixed>|null $gallery
     */
    private function galleryFileCount(?array $gallery): int
    {
        $names = $gallery['name'] ?? [];
        if (!is_array($names)) {
            return 0;
        }

        return count($names);
    }

    /**
     * @param array<string,mixed>|null $gallery
     * @return array<string,mixed>
     */
    private function galleryFileAt(?array $gallery, int $index): array
    {
        if (!is_array($gallery)) {
            return [];
        }

        return [
            'name' => $gallery['name'][$index] ?? '',
            'type' => $gallery['type'][$index] ?? '',
            'tmp_name' => $gallery['tmp_name'][$index] ?? '',
            'error' => $gallery['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $gallery['size'][$index] ?? 0,
        ];
    }
}
