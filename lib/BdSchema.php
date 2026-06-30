<?php
/**
 * Détection du schéma BD / Manga (table oeuvre_bd).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdSchema
{
    public static function tableExists(string $table = 'oeuvre_bd'): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = "
            . self::quoteSqlLiteral($table) . ' LIMIT 1'
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function hasColumn(string $column): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_bd)');
        if ($stmt === false) {
            return false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
