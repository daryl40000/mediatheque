-- Enrichissement IGDB : identifiant externe + date de dernière tentative.
ALTER TABLE oeuvre_jeu ADD COLUMN igdb_id INTEGER NOT NULL DEFAULT 0;
ALTER TABLE oeuvre_jeu ADD COLUMN igdb_enriched_at TEXT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_igdb_id ON oeuvre_jeu(igdb_id) WHERE igdb_id > 0;
