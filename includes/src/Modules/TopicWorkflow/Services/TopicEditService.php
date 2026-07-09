<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Services;

use App\Core\Events\EventDispatcher;
use App\Modules\TopicWorkflow\Events\TopicWorkflowEvent;
use PDO;
use RuntimeException;
use Throwable;

final class TopicEditService
{
    public function __construct(private readonly ?EventDispatcher $events = null)
    {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     * @return array{topic_id:int,slug:string,status:string}
     */
    public function update(PDO $pdo, array $input, array $files = []): array
    {
        $topicId = max(0, (int) ($input['topic_id'] ?? 0));
        $userId = max(0, (int) ($input['user_id'] ?? 0));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $authorTopic = trim((string) ($input['author_topic'] ?? ''));
        $topicVersion = trim((string) ($input['topic_version'] ?? ''));
        $content = (string) ($input['content'] ?? '');
        if ($content !== '') {
            $content = \sanitizeHtml($content);
        }
        $status = (string) ($input['status'] ?? 'draft');
        $moderationFlagsJson = $input['moderation_flags_json'] ?? null;
        $videoUrl = trim((string) ($input['video_url'] ?? ''));
        $downloadLines = trim((string) ($input['download_lines'] ?? ''));
        $maxImages = max(1, (int) ($input['max_images'] ?? 10));
        $topic = is_array($input['topic'] ?? null) ? $input['topic'] : [];
        $keepMediaIds = array_values(array_filter(
            array_map('intval', (array) ($input['keep_media_ids'] ?? [])),
            static fn (int $id): bool => $id > 0,
        ));

        $coverFile = $this->arrayOrNull($files['cover'] ?? null);
        $galleryFiles = $this->arrayOrNull($files['gallery'] ?? null);
        $attachmentFile = $this->arrayOrNull($files['attachment'] ?? null);

        try {
            $uploadedPaths = [];
            $pdo->beginTransaction();

            $existingMediaRecords = \getTopicMediaRecords($pdo, $topicId, true);
            $existingById = [];
            foreach (is_array($existingMediaRecords) ? $existingMediaRecords : [] as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $mediaId = (int) ($record['id'] ?? 0);
                if ($mediaId > 0) {
                    $existingById[$mediaId] = $record;
                }
            }

            foreach ($existingById as $mediaId => $record) {
                $type = (string) ($record['type'] ?? '');
                if ($type === 'video') {
                    \deleteTopicMediaRecord($pdo, $mediaId);
                    continue;
                }
                if (!in_array($mediaId, $keepMediaIds, true)) {
                    \deleteTopicMediaRecord($pdo, $mediaId);
                }
            }

            $slug = \generateUniqueSlug($pdo, $title, 'topics', $topicId);
            $primaryMediaId = !empty($topic['primary_media_file_id']) && in_array((int) $topic['primary_media_file_id'], $keepMediaIds, true)
                ? (int) $topic['primary_media_file_id']
                : null;

            if ($this->isUploadedFile($coverFile)) {
                if ($primaryMediaId) {
                    \deleteTopicMediaRecord($pdo, $primaryMediaId);
                }
                $cover = \handleFileUpload($pdo, $topicId, $coverFile, 'image', 0, true, $title, 1);
                if (isset($cover['error'])) {
                    throw new RuntimeException((string) $cover['error']);
                }
                if (isset($cover['path'])) {
                    $uploadedPaths[] = (string) $cover['path'];
                }
                $primaryMediaId = isset($cover['id']) ? (int) $cover['id'] : null;
            }

            if ($videoUrl !== '') {
                \createTopicMediaRecord($pdo, $topicId, $videoUrl, 'video', 1, false, 'remote');
            }

            $fileCount = min($maxImages, $this->galleryFileCount($galleryFiles));
            for ($i = 0; $i < $fileCount; $i++) {
                $singleFile = $this->galleryFileAt($galleryFiles, $i);
                if (!$this->isUploadedFile($singleFile)) {
                    continue;
                }

                $result = \handleFileUpload($pdo, $topicId, $singleFile, 'image', $i + 2, false, $title, $i + 2);
                if (isset($result['error'])) {
                    throw new RuntimeException((string) $result['error']);
                }
                if (isset($result['path'])) {
                    $uploadedPaths[] = (string) $result['path'];
                }
            }

            if ($this->isUploadedFile($attachmentFile)) {
                $attachment = \handleFileUpload($pdo, $topicId, $attachmentFile, 'attachment', 0, false, $title);
                if (isset($attachment['error'])) {
                    throw new RuntimeException((string) $attachment['error']);
                }
                if (isset($attachment['path'])) {
                    $uploadedPaths[] = (string) $attachment['path'];
                }
            }

            $pdo->prepare('UPDATE topics
                           SET category_id = :category_id,
                               title = :title,
                               slug = :slug,
                               author_topic = :author_topic,
                               topic_version = :topic_version,
                               topic_descriptions = :content,
                               primary_media_file_id = :primary_media_id,
                               status = :status,
                               moderation_flags = :moderation_flags,
                               updated_at = NOW()
                           WHERE id = :id AND author_id = :uid')
                ->execute([
                    'category_id' => $categoryId,
                    'title' => $title,
                    'slug' => $slug,
                    'author_topic' => $authorTopic !== '' ? $authorTopic : null,
                    'topic_version' => $topicVersion !== '' ? $topicVersion : null,
                    'content' => $content,
                    'primary_media_id' => $primaryMediaId,
                    'status' => $status,
                    'moderation_flags' => is_string($moderationFlagsJson) ? $moderationFlagsJson : null,
                    'id' => $topicId,
                    'uid' => $userId,
                ]);

            \syncTopicDownloadLinks($pdo, $topicId, $downloadLines);
            $pdo->commit();
            seoInvalidateSitemapCaches();

            $this->emitEvents($pdo, $topicId, $userId, $status, $categoryId, $slug, $title);

            return [
                'topic_id' => $topicId,
                'slug' => $slug,
                'status' => $status,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach (($uploadedPaths ?? []) as $uploadedPath) {
                \topicDeletePhysicalFile((string) $uploadedPath);
            }

            throw $exception;
        }
    }

    private function emitEvents(
        PDO $pdo,
        int $topicId,
        int $userId,
        string $status,
        int $categoryId,
        string $slug,
        string $title,
    ): void {
        if (!$this->events instanceof EventDispatcher) {
            return;
        }

        $payload = [
            'topic_id' => $topicId,
            'editor_user_id' => $userId,
            'status' => $status,
            'category_id' => $categoryId,
            'slug' => $slug,
            'title' => $title,
            'source' => 'topic_workflow.edit',
            'pdo' => $pdo,
        ];

        $this->events->dispatch(new TopicWorkflowEvent('topic.updated', $payload));

        if (in_array($status, ['published', 'approved'], true)) {
            $this->events->dispatch(new TopicWorkflowEvent('topic.published', $payload));
        }
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
