-- Périodes d’échelle de notation par plage de numéros (série magazine).
-- Ex. n°1–92 sur 5, n°93–110 sur 100. series.rating_scale reste le repli hors plage.

CREATE TABLE IF NOT EXISTS magazine_series_rating_period (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
    from_numero_ordre REAL NOT NULL,
    to_numero_ordre REAL DEFAULT NULL,
    rating_scale TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_msrp_series
    ON magazine_series_rating_period(series_id, sort_order, from_numero_ordre);
