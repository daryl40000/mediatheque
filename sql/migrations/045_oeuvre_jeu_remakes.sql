-- Remakes pour jeux vidéo : lien vers le jeu d'origine du catalogue.
-- Un remake reste une fiche jeu à part entière, mais référence le titre source.

ALTER TABLE oeuvre_jeu ADD COLUMN is_remake INTEGER NOT NULL DEFAULT 0;
ALTER TABLE oeuvre_jeu ADD COLUMN original_game_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_is_remake ON oeuvre_jeu(is_remake);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_original_game ON oeuvre_jeu(original_game_oeuvre_id);
