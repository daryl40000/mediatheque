-- Phase M5 (v0.2.0) — Séries en collection sans numéro obligatoire

CREATE TABLE IF NOT EXISTS series_bibliotheque (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL,
    foyer_id INTEGER NOT NULL,
    statut TEXT NOT NULL DEFAULT 'collection',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_bib_foyer
    ON series_bibliotheque(series_id, foyer_id)
    WHERE statut = 'collection';

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_bib_user
    ON series_bibliotheque(series_id, user_id)
    WHERE statut = 'wishlist';

CREATE INDEX IF NOT EXISTS idx_series_bib_series ON series_bibliotheque(series_id);

-- Rattacher les séries qui ont déjà des numéros en bibliothèque
INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
SELECT DISTINCT om.series_id, b.user_id, b.foyer_id, b.statut
FROM oeuvre_magazine om
INNER JOIN bibliotheque b ON b.oeuvre_id = om.oeuvre_id;
