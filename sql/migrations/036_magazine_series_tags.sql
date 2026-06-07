-- Phase M5 (v0.4.2) — Tags libres sur la série (remplace la liste figée de plateformes)

ALTER TABLE series RENAME COLUMN platforms TO tags;
