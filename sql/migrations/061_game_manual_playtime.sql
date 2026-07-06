-- Temps de jeu saisi manuellement (Battle.net, Epic hors Steam, etc.).

ALTER TABLE bibliotheque ADD COLUMN manual_playtime_minutes INTEGER NOT NULL DEFAULT 0;
