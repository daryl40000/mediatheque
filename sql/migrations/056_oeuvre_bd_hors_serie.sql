-- BD / Manga : ordre de tri décimal et flag hors-série (comme les magazines)

ALTER TABLE oeuvre_bd ADD COLUMN tome_ordre REAL NOT NULL DEFAULT 0;
ALTER TABLE oeuvre_bd ADD COLUMN est_hors_serie INTEGER NOT NULL DEFAULT 0;

UPDATE oeuvre_bd SET tome_ordre = CAST(tome_numero AS REAL)
WHERE tome_ordre = 0 AND tome_numero > 0;

CREATE INDEX IF NOT EXISTS idx_oeuvre_bd_series_ordre ON oeuvre_bd(series_id, tome_ordre);
