-- Prénom et pseudo pour le profil utilisateur.

ALTER TABLE utilisateurs ADD COLUMN prenom TEXT NOT NULL DEFAULT '';
ALTER TABLE utilisateurs ADD COLUMN pseudo TEXT NOT NULL DEFAULT '';
