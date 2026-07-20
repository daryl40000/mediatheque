<?php
/**
 * Filtre les paramètres nommés SQL (:name) pour n’envoyer à PDO que ceux utilisés.
 *
 * Évite les erreurs PDO quand un tableau de params contient des clés inutilisées
 * (JOIN / filtres optionnels assemblés dynamiquement).
 */

declare(strict_types=1);

namespace Moncine\Repository;

final class SqlNamedParams
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function filter(string $sql, array $params): array
    {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }

        $filtered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }

        return $filtered;
    }
}
