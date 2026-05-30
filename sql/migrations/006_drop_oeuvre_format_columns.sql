-- Retire les colonnes format du catalogue partagé (désormais sur bibliotheque uniquement).

ALTER TABLE oeuvres DROP COLUMN format_image;
ALTER TABLE oeuvres DROP COLUMN format_son;
