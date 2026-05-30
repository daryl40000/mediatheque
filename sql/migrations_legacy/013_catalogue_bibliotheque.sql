-- Catalogue Moncine (œuvres) + bibliothèque personnelle (collection / wishlist) + utilisateurs.
-- Migre les données depuis l’ancienne table films si elle existe.

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INTEGER PRIMARY KEY,
    nom TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT OR IGNORE INTO utilisateurs (id, nom) VALUES (1, 'Principal');

CREATE TABLE IF NOT EXISTS oeuvres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titre TEXT NOT NULL,
    titre_original TEXT DEFAULT '',
    realisateur TEXT DEFAULT '',
    duree_min INTEGER DEFAULT 0,
    format_image TEXT DEFAULT '',
    format_son TEXT DEFAULT '',
    styles TEXT DEFAULT '',
    annee INTEGER DEFAULT 0,
    nationalite TEXT DEFAULT '',
    tmdb_id INTEGER DEFAULT 0,
    tmdb_media_type TEXT DEFAULT '',
    tmdb_tv_kind TEXT DEFAULT '',
    realisateur_tmdb_id INTEGER DEFAULT 0,
    acteur_1 TEXT DEFAULT '',
    acteur_1_tmdb_id INTEGER DEFAULT 0,
    acteur_2 TEXT DEFAULT '',
    acteur_2_tmdb_id INTEGER DEFAULT 0,
    acteur_3 TEXT DEFAULT '',
    acteur_3_tmdb_id INTEGER DEFAULT 0,
    poster_url TEXT DEFAULT '',
    synopsis TEXT DEFAULT '',
    omdb_imdb_id TEXT DEFAULT '',
    omdb_enriched_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL,
    UNIQUE (titre, realisateur)
);

CREATE INDEX IF NOT EXISTS idx_oeuvres_tmdb ON oeuvres(tmdb_id) WHERE tmdb_id > 0;

CREATE TABLE IF NOT EXISTS bibliotheque (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL DEFAULT 1 REFERENCES utilisateurs(id),
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    statut TEXT NOT NULL DEFAULT 'collection' CHECK (statut IN ('collection', 'wishlist')),
    support_physique TEXT DEFAULT '',
    saga TEXT DEFAULT '',
    saga_ordre INTEGER DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (user_id, oeuvre_id)
);

CREATE INDEX IF NOT EXISTS idx_bibliotheque_user_statut ON bibliotheque(user_id, statut);
CREATE INDEX IF NOT EXISTS idx_bibliotheque_oeuvre ON bibliotheque(oeuvre_id);

-- Copie films → oeuvres (une seule fois, si oeuvres vide et films non vide)
INSERT INTO oeuvres (
    titre, titre_original, realisateur, duree_min, format_image, format_son, styles,
    annee, nationalite, tmdb_id, tmdb_media_type, tmdb_tv_kind,
    realisateur_tmdb_id, acteur_1, acteur_1_tmdb_id, acteur_2, acteur_2_tmdb_id,
    acteur_3, acteur_3_tmdb_id, poster_url, synopsis, omdb_imdb_id, omdb_enriched_at, created_at
)
SELECT
    titre, titre_original, realisateur, duree_min, format_image, format_son, styles,
    annee, nationalite, tmdb_id, tmdb_media_type, tmdb_tv_kind,
    realisateur_tmdb_id, acteur_1, acteur_1_tmdb_id, acteur_2, acteur_2_tmdb_id,
    acteur_3, acteur_3_tmdb_id, poster_url, synopsis, omdb_imdb_id, omdb_enriched_at, created_at
FROM films
WHERE (SELECT COUNT(*) FROM oeuvres) = 0
  AND (SELECT COUNT(*) FROM films) > 0;

-- Copie films → bibliotheque (conserve les mêmes id pour URLs et historique)
INSERT INTO bibliotheque (id, user_id, oeuvre_id, statut, support_physique, saga, saga_ordre, created_at)
SELECT
    f.id,
    1,
    o.id,
    'collection',
    f.support_physique,
    f.saga,
    f.saga_ordre,
    f.created_at
FROM films f
INNER JOIN oeuvres o ON o.titre = f.titre AND o.realisateur = f.realisateur
WHERE (SELECT COUNT(*) FROM bibliotheque) = 0
  AND (SELECT COUNT(*) FROM films) > 0;

-- Met à jour sqlite_sequence pour les prochains id bibliotheque
UPDATE sqlite_sequence
SET seq = (SELECT COALESCE(MAX(id), 0) FROM bibliotheque)
WHERE name = 'bibliotheque'
  AND (SELECT COUNT(*) FROM bibliotheque) > 0;
