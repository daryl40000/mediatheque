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

    /** Reconstruit l’index à partir de oeuvre_magazine (+ textes des suppléments). */
    public static function reindexAll(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $db = Database::getInstance();
        $db->exec('DELETE FROM magazine_issue_fts');
        $stmt = $db->query('SELECT oeuvre_id FROM oeuvre_magazine ORDER BY oeuvre_id ASC');
        if ($stmt === false) {
            return;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $oeuvreId) {
            self::upsert((int) $oeuvreId);
        }
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
        $pdfPreview = (string) ($row['pdf_text_preview'] ?? '');
        if (MagazineIssueSupplementRepository::isAvailable()) {
            $supplementText = (new MagazineIssueSupplementRepository())
                ->concatTextPreviewsForOeuvre($oeuvreId);
            if ($supplementText !== '') {
                $pdfPreview = trim($pdfPreview . "\n\n" . $supplementText);
            }
        }

        $insert = $db->prepare(
            'INSERT INTO magazine_issue_fts (oeuvre_id, series_id, numero, sommaire, pdf_text_preview, date_parution)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            (int) ($row['oeuvre_id'] ?? 0),
            (int) ($row['series_id'] ?? 0),
            (string) ($row['numero'] ?? ''),
            (string) ($row['sommaire'] ?? ''),
            $pdfPreview,
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
