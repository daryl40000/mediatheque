<?php
/**
 * Échappement des motifs LIKE SQL (%, _, \) pour éviter les recherches trop larges.
 */

declare(strict_types=1);

namespace Moncine;

final class LikePattern
{
    /**
     * Prépare un fragment pour LIKE … ESCAPE '\'
     * (ex. « % » seul ne renvoie plus tous les comptes).
     */
    public static function containsFragment(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        return '%' . self::escapeLiteral($query) . '%';
    }

    public static function escapeLiteral(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
