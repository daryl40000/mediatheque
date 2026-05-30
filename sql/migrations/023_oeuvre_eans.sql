-- Phase 6 bis : plusieurs EAN par œuvre catalogue (par support physique).

CREATE TABLE IF NOT EXISTS oeuvre_eans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    ean TEXT NOT NULL,
    support_physique TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT 'manual',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (oeuvre_id, support_physique),
    UNIQUE (ean)
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_eans_oeuvre ON oeuvre_eans(oeuvre_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_eans_ean ON oeuvre_eans(ean);
