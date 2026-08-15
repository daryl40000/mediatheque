-- Enrichissement Open Library : identifiant édition + date de dernière tentative.

ALTER TABLE oeuvre_livre ADD COLUMN openlibrary_id TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_livre ADD COLUMN ol_enriched_at TEXT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_oeuvre_livre_openlibrary_id
    ON oeuvre_livre(openlibrary_id) WHERE TRIM(openlibrary_id) != '';
