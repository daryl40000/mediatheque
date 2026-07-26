-- Fichiers joints jeux : du niveau bibliothèque (exemplaire) vers le catalogue (œuvre).
-- Ainsi un admin ajoute manuels/soluces une fois ; tout le monde en bénéficie.

CREATE TABLE IF NOT EXISTS game_attachment_by_oeuvre (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    stored_object_id INTEGER NOT NULL REFERENCES stored_objects(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    original_filename TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Bases déjà en production (colonne bibliotheque_id) : rattacher via bibliotheque.oeuvre_id.
INSERT OR IGNORE INTO game_attachment_by_oeuvre (
    id, oeuvre_id, stored_object_id, label, original_filename, created_at
)
SELECT ga.id, b.oeuvre_id, ga.stored_object_id, ga.label, ga.original_filename, ga.created_at
FROM game_attachment ga
INNER JOIN bibliotheque b ON b.id = ga.bibliotheque_id
WHERE b.oeuvre_id IS NOT NULL AND b.oeuvre_id > 0;

-- Install fraîche (schema déjà en oeuvre_id) : recopier les lignes existantes.
INSERT OR IGNORE INTO game_attachment_by_oeuvre (
    id, oeuvre_id, stored_object_id, label, original_filename, created_at
)
SELECT ga.id, ga.oeuvre_id, ga.stored_object_id, ga.label, ga.original_filename, ga.created_at
FROM game_attachment ga
WHERE ga.oeuvre_id IS NOT NULL AND ga.oeuvre_id > 0;

DROP TABLE IF EXISTS game_attachment;
ALTER TABLE game_attachment_by_oeuvre RENAME TO game_attachment;

CREATE INDEX IF NOT EXISTS idx_game_attachment_oeuvre ON game_attachment(oeuvre_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_game_attachment_object ON game_attachment(stored_object_id);
