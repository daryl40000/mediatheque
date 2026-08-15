-- Enrichissement Open Library sur les tomes BD / manga (édition, pas série).

ALTER TABLE oeuvre_bd ADD COLUMN isbn TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_bd ADD COLUMN pages INTEGER NOT NULL DEFAULT 0;
ALTER TABLE oeuvre_bd ADD COLUMN openlibrary_id TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_bd ADD COLUMN ol_enriched_at TEXT DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_isbn ON oeuvre_bd(isbn) WHERE TRIM(isbn) != '';
CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_openlibrary_id
    ON oeuvre_bd(openlibrary_id) WHERE TRIM(openlibrary_id) != '';
