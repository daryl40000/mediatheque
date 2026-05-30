-- Phase 4 : foyers (collection partagée entre membres d’un même ménage).

CREATE TABLE IF NOT EXISTS foyers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

ALTER TABLE utilisateurs ADD COLUMN foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id);

CREATE INDEX IF NOT EXISTS idx_utilisateurs_foyer ON utilisateurs(foyer_id);
