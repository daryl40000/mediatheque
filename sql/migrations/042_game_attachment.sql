-- Fichiers joints à un jeu (abandonware, patch, ISO…).

CREATE TABLE IF NOT EXISTS game_attachment (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    stored_object_id INTEGER NOT NULL REFERENCES stored_objects(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    original_filename TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_attachment_bib ON game_attachment(bibliotheque_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_game_attachment_object ON game_attachment(stored_object_id);
