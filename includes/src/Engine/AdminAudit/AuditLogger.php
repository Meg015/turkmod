<?php

declare(strict_types=1);

namespace App\Engine\AdminAudit;

use PDO;

final class AuditLogger
{
    public function logAction(
        PDO $pdo,
        string $actionType,
        string $targetType,
        int $targetId,
        string $reason,
        array $oldValue = [],
        array $newValue = [],
        bool $reversible = false
    ): int {
        return adminLogAction(
            $pdo,
            $actionType,
            $targetType,
            $targetId,
            $reason,
            $oldValue,
            $newValue,
            $reversible
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function getActionLog(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return adminGetActionLog($pdo, $filters, $limit, $offset);
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function countActionLog(PDO $pdo, array $filters = []): int
    {
        return adminCountActionLog($pdo, $filters);
    }

    public function revertAction(PDO $pdo, int $logId, int $actorId): string
    {
        return adminRevertAction($pdo, $logId, $actorId);
    }

    public function actionLabel(string $actionType): string
    {
        return adminActionLabel($actionType);
    }
}
