<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ReportDeletionService
{
    public function __construct(private ReportsSchemaService $schema) {}

    /** @return list<int> */
    public static function normalizeIds(array $reportIds): array
    {
        if ($reportIds === []) {
            throw new InvalidArgumentException('İşlem için en az bir rapor seçin.');
        }
        $ids = [];
        foreach ($reportIds as $value) {
            $id = (is_int($value) || is_string($value))
                ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            if ($id === false) {
                throw new InvalidArgumentException('Seçilen rapor kimlikleri geçersiz.');
            }
            $ids[$id] = $id;
        }
        return array_values($ids);
    }

    public function deleteSelected(PDO $pdo, string $scope, array $reportIds, ?int $actorId = null): int
    {
        $prefix = match ($scope) {
            'topics' => 'topic',
            'users' => 'user',
            default => throw new InvalidArgumentException('Geçersiz rapor türü.'),
        };
        $ids = self::normalizeIds($reportIds);
        if ($scope === 'topics') {
            $this->schema->ensureTopicReports($pdo);
        } else {
            $this->schema->ensureUserReports($pdo);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT report_selection_delete');
        }
        try {
            $rowLock = $this->schema->isSqlite($pdo) ? '' : ' FOR UPDATE';
            $selected = $pdo->prepare("SELECT id FROM {$prefix}_reports WHERE id IN ({$placeholders}) ORDER BY id{$rowLock}");
            $selected->execute($ids);
            $existingIds = $selected->fetchAll(PDO::FETCH_COLUMN);

            $events = $pdo->prepare("DELETE FROM {$prefix}_report_events WHERE report_id IN ({$placeholders})");
            $events->execute($ids);
            $reports = $pdo->prepare("DELETE FROM {$prefix}_reports WHERE id IN ({$placeholders})");
            $reports->execute($ids);
            $deletedCount = $reports->rowCount();

            if (function_exists('logActivity')) {
                foreach ($existingIds as $reportId) {
                    logActivity($pdo, $prefix . '_report_deleted', $prefix . '_report', (int) $reportId, ['actor_id' => $actorId]);
                }
            }
            if ($ownsTransaction) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT report_selection_delete');
            }
            return $deletedCount;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            } elseif (!$ownsTransaction && $pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT report_selection_delete');
                $pdo->exec('RELEASE SAVEPOINT report_selection_delete');
            }
            throw $exception;
        }
    }
}
