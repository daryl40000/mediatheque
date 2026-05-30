-- Versions recherchées sur une envie (support + EAN) — socle recherche d’achat en ligne.

CREATE TABLE IF NOT EXISTS wishlist_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    support_physique TEXT NOT NULL DEFAULT '',
    ean TEXT NOT NULL DEFAULT '',
    oeuvre_ean_id INTEGER DEFAULT NULL REFERENCES oeuvre_eans(id) ON DELETE SET NULL,
    label TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (bibliotheque_id, support_physique)
);

CREATE INDEX IF NOT EXISTS idx_wishlist_targets_bibliotheque ON wishlist_targets(bibliotheque_id);

CREATE INDEX IF NOT EXISTS idx_wishlist_targets_ean
    ON wishlist_targets(ean)
    WHERE ean != '';
