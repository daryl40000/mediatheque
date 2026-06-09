-- Phase M4 — Supports physiques (CD/DVD, disquette) et magasins démat (Steam, PSN…)

ALTER TABLE oeuvre_jeu ADD COLUMN physical_supports TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvre_jeu ADD COLUMN digital_stores TEXT NOT NULL DEFAULT '';
