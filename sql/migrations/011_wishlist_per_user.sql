-- Phase 4 : contraintes séparées collection (foyer) / envies (utilisateur).

CREATE TABLE bibliotheque_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL DEFAULT 1 REFERENCES utilisateurs(id),
    foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id),
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    statut TEXT NOT NULL DEFAULT 'collection' CHECK (statut IN ('collection', 'wishlist')),
    support_physique TEXT DEFAULT '',
    format_image TEXT DEFAULT '',
    format_son TEXT DEFAULT '',
    saga TEXT DEFAULT '',
    saga_ordre INTEGER DEFAULT 0,
    saison_numero INTEGER DEFAULT 0,
    saison_label TEXT DEFAULT '',
    ean TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO bibliotheque_new (
    id, user_id, foyer_id, oeuvre_id, statut, support_physique, format_image, format_son,
    saga, saga_ordre, saison_numero, saison_label, ean, created_at
)
SELECT
    id, user_id, foyer_id, oeuvre_id, statut, support_physique, format_image, format_son,
    saga, saga_ordre, saison_numero, saison_label, ean, created_at
FROM bibliotheque;

DROP TABLE bibliotheque;

ALTER TABLE bibliotheque_new RENAME TO bibliotheque;

CREATE INDEX IF NOT EXISTS idx_bibliotheque_user_statut ON bibliotheque(user_id, statut);
CREATE INDEX IF NOT EXISTS idx_bibliotheque_foyer_statut ON bibliotheque(foyer_id, statut);

CREATE UNIQUE INDEX IF NOT EXISTS idx_bibliotheque_foyer_collection
    ON bibliotheque(foyer_id, oeuvre_id)
    WHERE statut = 'collection' AND foyer_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_bibliotheque_user_wishlist
    ON bibliotheque(user_id, oeuvre_id)
    WHERE statut = 'wishlist';
