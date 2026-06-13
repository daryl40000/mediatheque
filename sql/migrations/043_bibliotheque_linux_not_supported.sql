-- Jeux PC : Linux testé OK vs Linux non supporté (mutuellement exclusifs).

ALTER TABLE bibliotheque ADD COLUMN linux_not_supported INTEGER NOT NULL DEFAULT 0;
