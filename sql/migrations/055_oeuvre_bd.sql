-- Phase M2 — BD / Manga : métadonnées catalogue (série, tome, auteurs)

CREATE TABLE IF NOT EXISTS oeuvre_bd (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    series_id INTEGER DEFAULT NULL REFERENCES series(id) ON DELETE SET NULL,
    kind TEXT NOT NULL DEFAULT 'bd',
    tome_numero INTEGER NOT NULL DEFAULT 0,
    tome_label TEXT NOT NULL DEFAULT '',
    scenariste TEXT NOT NULL DEFAULT '',
    dessinateur TEXT NOT NULL DEFAULT '',
    editeur TEXT NOT NULL DEFAULT '',
    genre TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_series ON oeuvre_bd(series_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_kind ON oeuvre_bd(kind);
CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_scenariste ON oeuvre_bd(scenariste COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_dessinateur ON oeuvre_bd(dessinateur COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_editeur ON oeuvre_bd(editeur COLLATE NOCASE);
