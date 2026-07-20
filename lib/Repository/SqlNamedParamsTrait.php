<?php
/**
 * Trait pour les repositories instance : délègue à {@see SqlNamedParams}.
 */

declare(strict_types=1);

namespace Moncine\Repository;

trait SqlNamedParamsTrait
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function filterParamsForSql(string $sql, array $params): array
    {
        return SqlNamedParams::filter($sql, $params);
    }
}
