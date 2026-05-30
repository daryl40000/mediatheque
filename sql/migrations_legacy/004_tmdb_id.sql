-- Identifiant TMDB (correction manuelle sur la fiche film)
ALTER TABLE films ADD COLUMN tmdb_id INTEGER DEFAULT 0;
