-- Phase M4 — Jeux vidéo : catalogue oeuvre_jeu + lien optionnel sujet magazine → fiche jeu

CREATE TABLE IF NOT EXISTS oeuvre_jeu (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    studio TEXT NOT NULL DEFAULT '',
    editeur TEXT NOT NULL DEFAULT '',
    genre TEXT NOT NULL DEFAULT '',
    platform TEXT NOT NULL DEFAULT '',
    is_digital INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_platform ON oeuvre_jeu(platform);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_studio ON oeuvre_jeu(studio COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_genre ON oeuvre_jeu(genre COLLATE NOCASE);

-- Pont magazines ↔ jeux : sujet test/preview/interview → oeuvres.id (media_domain = jeu)
ALTER TABLE magazine_subject ADD COLUMN catalog_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_magazine_subject_catalog_oeuvre ON magazine_subject(catalog_oeuvre_id);
