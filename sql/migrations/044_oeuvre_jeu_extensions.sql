-- Extensions (DLC / add-on) pour jeux vidéo : lien vers un jeu de base du catalogue.
-- Une extension reste une fiche jeu à part entière, mais peut référencer un jeu principal.

ALTER TABLE oeuvre_jeu ADD COLUMN is_extension INTEGER NOT NULL DEFAULT 0;
ALTER TABLE oeuvre_jeu ADD COLUMN base_game_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_is_extension ON oeuvre_jeu(is_extension);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_base_game ON oeuvre_jeu(base_game_oeuvre_id);

