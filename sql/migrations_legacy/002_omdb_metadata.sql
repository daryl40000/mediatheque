-- Métadonnées OMDb (affiche + synopsis)
ALTER TABLE films ADD COLUMN poster_url TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN synopsis TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN omdb_imdb_id TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN omdb_enriched_at TEXT DEFAULT NULL;
