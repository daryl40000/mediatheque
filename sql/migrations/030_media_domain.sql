-- Phase M0 — Domaine média (films, BD, livres, jeux, magazines)

ALTER TABLE oeuvres ADD COLUMN media_domain TEXT NOT NULL DEFAULT 'film';

UPDATE oeuvres SET media_domain = 'film' WHERE TRIM(COALESCE(media_domain, '')) = '';

CREATE INDEX IF NOT EXISTS idx_oeuvres_media_domain ON oeuvres(media_domain);
