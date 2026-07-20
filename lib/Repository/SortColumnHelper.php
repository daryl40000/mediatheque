<?php
/**
 * Validation / normalisation d’une colonne de tri SQL.
 */

declare(strict_types=1);

namespace Moncine\Repository;

final class SortColumnHelper
{
    /**
     * @param array<string, string> $columns map clé tri → expression SQL
     */
    public static function resolve(string $sortBy, array $columns, string $default = 'titre'): string
    {
        return isset($columns[$sortBy]) ? $sortBy : $default;
    }

    /**
     * @param array<string, string> $columns
     */
    public static function expression(string $sortBy, array $columns, string $default = 'titre'): string
    {
        $key = self::resolve($sortBy, $columns, $default);

        return $columns[$key] ?? $columns[$default] ?? reset($columns) ?: '';
    }

    public static function direction(string $sortDir): string
    {
        return strtolower(trim($sortDir)) === 'desc' ? 'DESC' : 'ASC';
    }
}
