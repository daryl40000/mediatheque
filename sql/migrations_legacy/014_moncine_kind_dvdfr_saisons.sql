-- Catégorie Moncine (film / série / spectacle), saisons, lien DVDFr.

ALTER TABLE oeuvres ADD COLUMN moncine_kind TEXT DEFAULT 'film';
ALTER TABLE bibliotheque ADD COLUMN saison_numero INTEGER DEFAULT 0;
ALTER TABLE bibliotheque ADD COLUMN saison_label TEXT DEFAULT '';
ALTER TABLE bibliotheque ADD COLUMN dvdfr_id INTEGER DEFAULT 0;
ALTER TABLE bibliotheque ADD COLUMN ean TEXT DEFAULT '';

ALTER TABLE films ADD COLUMN moncine_kind TEXT DEFAULT 'film';
ALTER TABLE films ADD COLUMN saison_numero INTEGER DEFAULT 0;
ALTER TABLE films ADD COLUMN saison_label TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN dvdfr_id INTEGER DEFAULT 0;
ALTER TABLE films ADD COLUMN ean TEXT DEFAULT '';
