<?php
declare(strict_types=1);

namespace App\Core\Http;

/** Query parameters consumed by this application are scalar values. */
final class QueryInput
{
    public static function isValid(array $query): bool
    {
        foreach ($query as $value) {
            if (!is_scalar($value) && $value !== null) {
                return false;
            }
        }
        if (isset($query['page']) && filter_var($query['page'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]) === false) {
            return false;
        }
        return true;
    }
}
