-- Page d’article sur le lien numéro magazine ↔ sujet (pour ouvrir le PDF à la bonne page).

ALTER TABLE oeuvre_magazine_subject ADD COLUMN page INTEGER NOT NULL DEFAULT 0;
