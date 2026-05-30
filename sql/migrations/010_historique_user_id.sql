-- Phase 4 : historique de vision personnel par utilisateur.

ALTER TABLE historique ADD COLUMN user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id);

CREATE INDEX IF NOT EXISTS idx_historique_user ON historique(user_id);
CREATE INDEX IF NOT EXISTS idx_historique_film_user ON historique(film_id, user_id);
