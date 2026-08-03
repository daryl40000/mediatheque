-- Échelle de notation par série magazine + note brute sur les liens sujet (tests).

ALTER TABLE series ADD COLUMN rating_scale TEXT DEFAULT NULL;

ALTER TABLE oeuvre_magazine_subject ADD COLUMN score REAL DEFAULT NULL;

ALTER TABLE magazine_supplement_subject ADD COLUMN score REAL DEFAULT NULL;
