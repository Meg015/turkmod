<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Api;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Throwable;

final class LeaderboardApi implements Handler
{
    public function handle(Request $request): Response
    {
        $projectRoot = dirname(__DIR__, 5);

        require_once $projectRoot . '/includes/src/Modules/Leaderboard/Support/helpers.php';
        require_once $projectRoot . '/includes/src/Modules/Leaderboard/Support/cache-manager.php';
        require_once $projectRoot . '/includes/src/Modules/Leaderboard/Support/calculator.php';

        $pdo = requireDatabaseConnection($GLOBALS['pdo'] ?? null);
        $GLOBALS['pdo'] = $pdo;

        $clientKey = 'api_leaderboard_' . ($request->serverParam('REMOTE_ADDR', 'guest') ?? 'guest');
        $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
        $leaderboardRateLimit = max(1, (int) ($settings['api_leaderboard_rate_limit'] ?? 60));
        $leaderboardRateWindow = max(1, (int) ($settings['api_leaderboard_rate_window'] ?? 1));

        if (!checkRateLimit($clientKey, $leaderboardRateLimit, $leaderboardRateWindow)) {
            return new JsonResponse(
                ['success' => false, 'error' => 'rate_limited'],
                429,
                ['Retry-After' => (string) max(60, $leaderboardRateWindow * 60)],
            );
        }

        incrementRateLimit($clientKey, $leaderboardRateWindow);

        $query = $request->getQuery();
        $category = $query['category'] ?? null;
        $period = $query['period'] ?? null;
        $limit = array_key_exists('limit', $query) ? (int) $query['limit'] : 50;
        $offset = array_key_exists('offset', $query) ? (int) $query['offset'] : 0;
        $search = isset($query['search']) ? trim((string) $query['search']) : '';

        if (!$category || !$period) {
            return new JsonResponse([
                'success' => false,
                'error' => 'missing_parameters',
                'message' => 'Both category and period parameters are required',
            ], 400);
        }

        $categoryValue = (string) $category;
        $periodValue = (string) $period;

        $validCategories = leaderboardGetValidCategories();
        if (!in_array($categoryValue, $validCategories, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_category',
                'message' => 'Category must be one of: ' . implode(', ', $validCategories),
            ], 400);
        }

        $validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all_time'];
        if (!in_array($periodValue, $validPeriods, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'invalid_period',
                'message' => 'Period must be one of: ' . implode(', ', $validPeriods),
            ], 400);
        }

        $limit = max(10, min(100, $limit));
        $offset = max(0, $offset);

        try {
            $data = leaderboardGetData($pdo, $categoryValue, $periodValue, $limit, $offset, $search !== '' ? $search : null);
            $periodDates = leaderboardGetPeriodDates($periodValue);

            $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
            $responseData = array_map(static function (array $row): array {
                $row['count'] = (int) ($row['count'] ?? $row['score'] ?? 0);
                unset($row['score']);

                return $row;
            }, $rows);

            return new JsonResponse([
                'success' => true,
                'category' => $categoryValue,
                'period' => $periodValue,
                'data' => $responseData,
                'total' => (int) ($data['total'] ?? 0),
                'limit' => $limit,
                'offset' => $offset,
                'calculated_at' => (string) ($data['calculated_at'] ?? date('Y-m-d H:i:s')),
                'is_cached' => (bool) ($data['is_cached'] ?? false),
                'period_range' => [
                    'start' => (string) ($periodDates['start_date'] ?? ''),
                    'end' => (string) ($periodDates['end_date'] ?? ''),
                ],
            ]);
        } catch (Throwable $exception) {
            appLogException($exception, ['source' => 'api/leaderboard.php']);

            return new JsonResponse([
                'success' => false,
                'error' => 'server_error',
                'message' => 'An error occurred while fetching leaderboard data',
            ], 500);
        }
    }
}

