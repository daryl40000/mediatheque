-- Lien « consulter en ligne » (ex. Abandonware Magazines) — série et numéro.

ALTER TABLE series ADD COLUMN external_url TEXT NOT NULL DEFAULT '';

ALTER TABLE oeuvre_magazine ADD COLUMN external_url TEXT NOT NULL DEFAULT '';
