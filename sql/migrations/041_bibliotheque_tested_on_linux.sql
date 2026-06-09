-- Jeux PC : indicateur « testé sous Linux » (bibliothèque / collection).

ALTER TABLE bibliotheque ADD COLUMN tested_on_linux INTEGER NOT NULL DEFAULT 0;
