<?php
/**
 * Schéma de la table historique (visions).
 * Corrige les bases migrées où film_id référençait encore films(id) au lieu de bibliotheque(id).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class HistoriqueSchema
{
    public static function repairForeignKeyIfNeeded(PDO $db): void
    {
        if (!CatalogSchema::usesCatalogTables($db)) {
            return;
        }

        $ddl = (string) $db->query(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'historique'"
        )->fetchColumn();

        if ($ddl === '' || stripos($ddl, 'REFERENCES bibliotheque') !== false) {
            return;
        }

        $db->exec('PRAGMA foreign_keys = OFF');

        $db->exec(
            'CREATE TABLE historique_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                film_id INTEGER NOT NULL,
                date_vue TEXT NOT NULL DEFAULT (date(\'now\')),
                note INTEGER,
                FOREIGN KEY (film_id) REFERENCES bibliotheque(id) ON DELETE CASCADE
            )'
        );

        $db->exec(
            'INSERT INTO historique_new (id, film_id, date_vue, note)
             SELECT h.id, h.film_id, h.date_vue, h.note
             FROM historique h
             WHERE EXISTS (SELECT 1 FROM bibliotheque b WHERE b.id = h.film_id)'
        );

        $db->exec('DROP TABLE historique');
        $db->exec('ALTER TABLE historique_new RENAME TO historique');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_historique_film ON historique(film_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_historique_date ON historique(date_vue)');

        $seq = $db->query('SELECT COALESCE(MAX(id), 0) FROM historique')->fetchColumn();
        if ($seq !== false && (int) $seq > 0) {
            $db->exec('DELETE FROM sqlite_sequence WHERE name = \'historique\'');
            $stmt = $db->prepare('INSERT INTO sqlite_sequence (name, seq) VALUES (\'historique\', ?)');
            $stmt->execute([(int) $seq]);
        }

        $db->exec('PRAGMA foreign_keys = ON');
    }
}
