-- Type TMDB : film (movie) ou série TV (tv) — même identifiant numérique possible selon le type.
ALTER TABLE films ADD COLUMN tmdb_media_type TEXT DEFAULT '';
