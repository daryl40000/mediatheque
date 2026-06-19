-- Métadonnées IGDB complémentaires (franchise, modes, thèmes, acronymes).
ALTER TABLE oeuvre_jeu ADD COLUMN franchise TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_jeu ADD COLUMN game_mode TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_jeu ADD COLUMN theme TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_jeu ADD COLUMN alternative_names TEXT NOT NULL DEFAULT '';

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_franchise ON oeuvre_jeu(franchise COLLATE NOCASE) WHERE franchise != '';
