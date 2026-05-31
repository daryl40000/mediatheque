-- Phase M5 (v0.2.0) — Séries de magazines + numéros (catalogue partagé)

CREATE TABLE IF NOT EXISTS series (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_domain TEXT NOT NULL DEFAULT 'magazine',
    titre TEXT NOT NULL,
    publication_type TEXT NOT NULL DEFAULT 'mensuel',
    poster_url TEXT DEFAULT '',
    editeur TEXT DEFAULT '',
    issn TEXT DEFAULT '',
    langue TEXT DEFAULT '',
    pays TEXT DEFAULT '',
    date_debut TEXT DEFAULT NULL,
    date_fin TEXT DEFAULT NULL,
    notes TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_domain_titre
    ON series(media_domain, titre COLLATE NOCASE);

CREATE INDEX IF NOT EXISTS idx_series_media_domain ON series(media_domain);

CREATE TABLE IF NOT EXISTS oeuvre_magazine (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
    numero TEXT NOT NULL DEFAULT '',
    numero_ordre REAL NOT NULL DEFAULT 0,
    date_parution TEXT DEFAULT NULL,
    sommaire TEXT DEFAULT '',
    pages INTEGER NOT NULL DEFAULT 0,
    est_hors_serie INTEGER NOT NULL DEFAULT 0,
    stored_object_id INTEGER DEFAULT NULL REFERENCES stored_objects(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_magazine_series ON oeuvre_magazine(series_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_magazine_series_ordre ON oeuvre_magazine(series_id, numero_ordre);
