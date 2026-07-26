<?php
/**
 * Helpers pour des listes PHP indexées 0..n (exigence PHPStan `list<…>`).
 *
 * PDO::fetchAll() et array_map() sont typés « array » générique ; PHPStan
 * demande souvent `list<T>`. Ces helpers reconstruisent une vraie liste.
 */

declare(strict_types=1);

namespace Moncine;

final class ListOf
{
    /**
     * @param array<mixed> $rows
     * @return list<array<string, mixed>>
     */
    public static function assocRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $assoc = [];
            foreach ($row as $key => $value) {
                $assoc[(string) $key] = $value;
            }
            $list[] = $assoc;
        }

        return $list;
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    public static function strings(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $list[] = (string) $value;
        }

        return $list;
    }

    /**
     * @param array<mixed> $values
     * @return list<int>
     */
    public static function ints(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $list[] = (int) $value;
        }

        return $list;
    }
}
