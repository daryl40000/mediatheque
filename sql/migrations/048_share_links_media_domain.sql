-- Partage visiteur : distinguer films et jeux (liens existants = films).

ALTER TABLE share_links ADD COLUMN media_domain TEXT NOT NULL DEFAULT 'film';

UPDATE share_links SET media_domain = 'film' WHERE TRIM(COALESCE(media_domain, '')) = '';

CREATE INDEX IF NOT EXISTS idx_share_links_media_domain ON share_links(media_domain);
