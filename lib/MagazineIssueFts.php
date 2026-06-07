<?php
/**
 * Index FTS5 des numéros magazine (n°, sommaire, extrait PDF, date).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineIssueFts
{
    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_issue_fts' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function matchExpression(string $query): string
    {
        return MagazineFtsQuery::matchExpression($query);
    }

    /** Reconstruit l’index à partir de oeuvre_magazine (maintenance). */
    public static function reindexAll(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $db = Database::getInstance();
        $db->exec('DELETE FROM magazine_issue_fts');
        $db->exec(
            'INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution)
             SELECT om.oeuvre_id,
                    om.series_id,
                    COALESCE(om.numero, \'\'),
                    COALESCE(om.sommaire, \'\'),
                    COALESCE(om.pdf_text_preview, \'\'),
                    COALESCE(om.date_parution, \'\')
             FROM oeuvre_magazine om'
        );
    }

    public static function upsert(int $oeuvreId): void
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution
             FROM oeuvre_magazine
             WHERE oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            self::delete($oeuvreId);

            return;
        }

        self::delete($oeuvreId);
        $insert = $db->prepare(
            'INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            (int) ($row['oeuvre_id'] ?? 0),
            (int) ($row['series_id'] ?? 0),
            (string) ($row['numero'] ?? ''),
            (string) ($row['sommaire'] ?? ''),
            (string) ($row['pdf_text_preview'] ?? ''),
            (string) ($row['date_parution'] ?? ''),
        ]);
    }

    public static function delete(int $oeuvreId): void
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return;
        }

        Database::getInstance()->prepare('DELETE FROM magazine_issue_fts WHERE oeuvre_id = ?')
            ->execute([$oeuvreId]);
    }
}
