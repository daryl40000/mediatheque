-- Catégories de série magazine (Jeux vidéo, Cinéma…) — héritées par tous les numéros.

ALTER TABLE series ADD COLUMN categories TEXT NOT NULL DEFAULT '';
