-- Identifiants TMDB des personnes (réalisateur + acteurs) pour enrichissements futurs sans ambiguïté
ALTER TABLE films ADD COLUMN realisateur_tmdb_id INTEGER DEFAULT 0;
ALTER TABLE films ADD COLUMN acteur_1_tmdb_id INTEGER DEFAULT 0;
ALTER TABLE films ADD COLUMN acteur_2_tmdb_id INTEGER DEFAULT 0;
ALTER TABLE films ADD COLUMN acteur_3_tmdb_id INTEGER DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_films_tmdb_id ON films(tmdb_id);
