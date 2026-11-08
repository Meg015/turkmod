<?php
declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Services;

use App\Modules\Messages\Services\MessageService;
use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Notifications\Services\NotificationTemplateService;
use PDO;
use Throwable;

final class TopicModerationNotificationService
{
    /** @return array{sent:list<string>,failed:list<string>} */
    public function sendRevision(PDO $pdo, array $topic, int $actorId, string $actorName, string $note, string $link, array $channels): array
    {
        $result = ['sent' => [], 'failed' => []];
        $recipientId = (int) ($topic['author_id'] ?? 0);
        $topicId = (int) ($topic['id'] ?? 0);
        $title = (string) ($topic['title'] ?? 'Konu');
        $message = '"' . $title . '" konunuz için revizyon istendi.' . "\n\n" . $note;
        $deliveryId = bin2hex(random_bytes(12));
        foreach (array_intersect(['in_app', 'email', 'message'], $channels) as $channel) {
            try {
                if ($recipientId <= 0 || $topicId <= 0) throw new \RuntimeException('Konu yazarı bulunamadı.');
                if ($channel === 'message') {
                    // A selected private message must not fan out to unselected email/bell channels.
                    $sent = (new MessageService())->sendMessage($pdo, $actorId, $recipientId,
                        $message . "\n\nKonuyu düzenle: " . $link, (string) ($GLOBALS['baseUri'] ?? ''), false);
                    $ok = !empty($sent['success']);
                } else {
                    $emailBody = $message;
                    if ($channel === 'email') {
                        $templates = new NotificationTemplateService();
                        $template = $templates->get($pdo, 'topic_revision_requested');
                        if ($template && $templates->emailBodyFormat($template) === 'html') {
                            $emailBody = nl2br(htmlspecialchars($emailBody, ENT_QUOTES, 'UTF-8'));
                        }
                    }
                    $ok = (new NotificationDispatchService())->dispatch($pdo, 'topic_revision_requested', $recipientId,
                        $actorId ?: null, 'topic', $topicId, [
                            'actor_name' => $actorName,
                            'topic_title' => $title,
                            'moderation_note_line' => ' Not: ' . $note,
                            'message' => $message,
                            'link' => $link,
                            'email_body' => $emailBody,
                            'email_link' => $link,
                            'dedupe_key' => 'topic_revision_requested:' . $topicId . ':' . $deliveryId . ':' . $channel,
                        ], [$channel]);
                }
                $result[$ok ? 'sent' : 'failed'][] = $channel;
            } catch (Throwable $exception) {
                $result['failed'][] = $channel;
                if (function_exists('appLogException')) appLogException($exception, ['source' => self::class, 'topic_id' => $topicId, 'channel' => $channel]);
            }
        }
        return $result;
    }
}
