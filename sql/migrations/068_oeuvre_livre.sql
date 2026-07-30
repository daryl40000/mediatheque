-- Phase M3 — Livres : métadonnées catalogue + liens vers jeux (catégorie Jeux vidéo).

CREATE TABLE IF NOT EXISTS oeuvre_livre (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    auteur TEXT NOT NULL DEFAULT '',
    isbn TEXT NOT NULL DEFAULT '',
    pages INTEGER NOT NULL DEFAULT 0,
    editeur TEXT NOT NULL DEFAULT '',
    categories TEXT NOT NULL DEFAULT '',
    langue TEXT NOT NULL DEFAULT 'fr',
    collection_label TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_livre_auteur ON oeuvre_livre(auteur COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_livre_editeur ON oeuvre_livre(editeur COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_livre_isbn ON oeuvre_livre(isbn);

-- Liens livre → jeux catalogue (quand le livre parle de jeux vidéo).
CREATE TABLE IF NOT EXISTS livre_game_link (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    game_oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (oeuvre_id, game_oeuvre_id)
);

CREATE INDEX IF NOT EXISTS idx_livre_game_link_oeuvre ON livre_game_link(oeuvre_id);
CREATE INDEX IF NOT EXISTS idx_livre_game_link_game ON livre_game_link(game_oeuvre_id);
