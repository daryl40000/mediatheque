<?php
/**
 * Détection du schéma jeux (tables / colonnes) — migrations progressives.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameSchema
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function tableExists(string $table = 'oeuvre_jeu'): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = " . self::quoteSqlLiteral($table) . ' LIMIT 1'
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }

        if (!self::tableExists($table)) {
            return self::$columnCache[$key] = false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(' . self::quoteIdentifier($table) . ')');
        if ($stmt === false) {
            return self::$columnCache[$key] = false;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (($row['name'] ?? '') === $column) {
                return self::$columnCache[$key] = true;
            }
        }

        return self::$columnCache[$key] = false;
    }

    public static function hasEditionColumns(): bool
    {
        return self::tableExists() && self::hasColumn('oeuvre_jeu', 'physical_supports');
    }

    public static function hasExtensionColumns(): bool
    {
        return self::hasColumn('oeuvre_jeu', 'is_extension')
            && self::hasColumn('oeuvre_jeu', 'base_game_oeuvre_id');
    }

    public static function hasRemakeColumns(): bool
    {
        return self::hasColumn('oeuvre_jeu', 'is_remake')
            && self::hasColumn('oeuvre_jeu', 'original_game_oeuvre_id');
    }

    public static function hasTestedOnLinuxColumn(): bool
    {
        return self::hasColumn('bibliotheque', 'tested_on_linux');
    }

    public static function hasLinuxNotSupportedColumn(): bool
    {
        return self::hasColumn('bibliotheque', 'linux_not_supported');
    }

    public static function hasNonPretableColumn(): bool
    {
        return self::hasColumn('bibliotheque', 'non_pretable');
    }

    public static function hasGamePlatformTable(): bool
    {
        return self::tableExists('game_platform');
    }

    public static function hasPlatformsColumn(): bool
    {
        return self::hasColumn('oeuvre_jeu', 'platforms');
    }

    public static function hasOwnedPlatformsColumn(): bool
    {
        return self::hasColumn('bibliotheque', 'owned_platforms');
    }

    public static function hasIgdbColumns(): bool
    {
        return self::hasColumn('oeuvre_jeu', 'igdb_id')
            && self::hasColumn('oeuvre_jeu', 'igdb_enriched_at');
    }

    public static function hasIgdbMetadataColumns(): bool
    {
        return self::hasColumn('oeuvre_jeu', 'franchise')
            && self::hasColumn('oeuvre_jeu', 'game_mode')
            && self::hasColumn('oeuvre_jeu', 'theme')
            && self::hasColumn('oeuvre_jeu', 'alternative_names');
    }

    /** @internal Tests PHPUnit */
    public static function resetColumnCacheForTests(): void
    {
        self::$columnCache = [];
    }

    private static function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
