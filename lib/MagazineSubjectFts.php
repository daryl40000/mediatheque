<?php
/**
 * Index FTS5 du catalogue de sujets magazines.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineSubjectFts
{
    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_subject_fts' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function matchExpression(string $query): string
    {
        return MagazineFtsQuery::matchExpression($query);
    }

    public static function reindexAll(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        $db = Database::getInstance();
        $db->exec('DELETE FROM magazine_subject_fts');
        $db->exec(
            'INSERT INTO magazine_subject_fts (subject_id, category, label, detail)
             SELECT id, category, label, COALESCE(detail, \'\')
             FROM magazine_subject'
        );
    }

    public static function upsert(int $subjectId): void
    {
        if (!self::isAvailable() || $subjectId <= 0) {
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, category, label, detail FROM magazine_subject WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$subjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            self::delete($subjectId);

            return;
        }

        self::delete($subjectId);
        $db->prepare(
            'INSERT INTO magazine_subject_fts (subject_id, category, label, detail)
             VALUES (?, ?, ?, ?)'
        )->execute([
            (int) ($row['id'] ?? 0),
            (string) ($row['category'] ?? ''),
            (string) ($row['label'] ?? ''),
            (string) ($row['detail'] ?? ''),
        ]);
    }

    public static function delete(int $subjectId): void
    {
        if (!self::isAvailable() || $subjectId <= 0) {
            return;
        }

        Database::getInstance()->prepare('DELETE FROM magazine_subject_fts WHERE subject_id = ?')
            ->execute([$subjectId]);
    }
}
