-- Sujets rattachés à un supplément magazine (comme oeuvre_magazine_subject pour le numéro).

CREATE TABLE IF NOT EXISTS magazine_supplement_subject (
    supplement_id INTEGER NOT NULL REFERENCES magazine_issue_supplement(id) ON DELETE CASCADE,
    subject_id INTEGER NOT NULL REFERENCES magazine_subject(id) ON DELETE CASCADE,
    page INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (supplement_id, subject_id)
);

CREATE INDEX IF NOT EXISTS idx_mss_subject ON magazine_supplement_subject(subject_id);
CREATE INDEX IF NOT EXISTS idx_mss_supplement ON magazine_supplement_subject(supplement_id);
