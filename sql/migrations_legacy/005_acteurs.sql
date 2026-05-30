-- Acteurs principaux (pour filtrage futur par acteur)
ALTER TABLE films ADD COLUMN acteur_1 TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN acteur_2 TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN acteur_3 TEXT DEFAULT '';

CREATE INDEX IF NOT EXISTS idx_films_realisateur ON films(realisateur);
CREATE INDEX IF NOT EXISTS idx_films_acteur_1 ON films(acteur_1);
CREATE INDEX IF NOT EXISTS idx_films_acteur_2 ON films(acteur_2);
CREATE INDEX IF NOT EXISTS idx_films_acteur_3 ON films(acteur_3);
