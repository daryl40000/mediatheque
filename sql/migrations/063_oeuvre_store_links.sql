-- Enrichissement liens magasins (GOG, Epic…) sur le catalogue jeux.

CREATE TABLE IF NOT EXISTS oeuvre_store_links (
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    store TEXT NOT NULL,
    store_slug TEXT NOT NULL DEFAULT '',
    store_url TEXT NOT NULL DEFAULT '',
    store_title TEXT NOT NULL DEFAULT '',
    match_confidence REAL,
    manually_verified INTEGER NOT NULL DEFAULT 0,
    last_verified_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (oeuvre_id, store)
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_store_links_unverified
    ON oeuvre_store_links(store, manually_verified)
    WHERE manually_verified = 0;
