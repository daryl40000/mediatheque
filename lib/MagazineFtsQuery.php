<?php
/**
 * Construction de requêtes MATCH pour les index FTS5 magazines.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineFtsQuery
{
    /**
     * Transforme une saisie utilisateur en expression FTS5 (préfixe par mot).
     * Ex. « gran turismo » → "gran"* AND "turismo"*
     */
    public static function matchExpression(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        $parts = [];
        foreach (preg_split('/\s+/u', $query) ?: [] as $token) {
            $token = self::normalizeToken($token);
            if ($token === '') {
                continue;
            }
            $parts[] = '"' . str_replace('"', '""', $token) . '"*';
        }

        return implode(' AND ', $parts);
    }

    private static function normalizeToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $token = str_replace(['"', "'", '*'], '', $token);

        return trim($token);
    }
}
