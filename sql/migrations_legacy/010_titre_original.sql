-- Titre original TMDB (souvent en anglais), affiché en sous-titre sur la fiche.
ALTER TABLE films ADD COLUMN titre_original TEXT DEFAULT '';
