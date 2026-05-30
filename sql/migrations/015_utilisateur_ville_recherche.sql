-- Ville optionnelle et visibilité dans la recherche d'utilisateurs (phase 6 amis).

ALTER TABLE utilisateurs ADD COLUMN ville TEXT NOT NULL DEFAULT '';
ALTER TABLE utilisateurs ADD COLUMN searchable INTEGER NOT NULL DEFAULT 1;
