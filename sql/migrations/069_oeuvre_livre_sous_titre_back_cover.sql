-- Livres : sous-titre + 4e de couverture.

ALTER TABLE oeuvre_livre ADD COLUMN sous_titre TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_livre ADD COLUMN back_cover_url TEXT NOT NULL DEFAULT '';
