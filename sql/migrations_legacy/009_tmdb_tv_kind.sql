-- Sous-type TV TMDB : series, documentary, emission, miniseries (vide pour les films).
ALTER TABLE films ADD COLUMN tmdb_tv_kind TEXT DEFAULT '';
