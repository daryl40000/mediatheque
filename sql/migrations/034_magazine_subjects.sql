-- Phase M5 (v0.4.0) — Sujets magazines (tests jeux, voitures, matériel…)

CREATE TABLE IF NOT EXISTS magazine_subject (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    label TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_magazine_subject_unique
    ON magazine_subject(category, label COLLATE NOCASE, detail COLLATE NOCASE);

CREATE INDEX IF NOT EXISTS idx_magazine_subject_category ON magazine_subject(category);
CREATE INDEX IF NOT EXISTS idx_magazine_subject_label ON magazine_subject(label COLLATE NOCASE);

CREATE TABLE IF NOT EXISTS oeuvre_magazine_subject (
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    subject_id INTEGER NOT NULL REFERENCES magazine_subject(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (oeuvre_id, subject_id)
);

CREATE INDEX IF NOT EXISTS idx_oms_subject ON oeuvre_magazine_subject(subject_id);
CREATE INDEX IF NOT EXISTS idx_oms_oeuvre ON oeuvre_magazine_subject(oeuvre_id);
