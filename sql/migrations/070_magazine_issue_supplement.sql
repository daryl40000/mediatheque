-- Suppléments / livrets bonus PDF rattachés à un numéro magazine.

CREATE TABLE IF NOT EXISTS magazine_issue_supplement (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    stored_object_id INTEGER NOT NULL REFERENCES stored_objects(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    cover_url TEXT NOT NULL DEFAULT '',
    pdf_text_preview TEXT NOT NULL DEFAULT '',
    pages INTEGER NOT NULL DEFAULT 0,
    original_filename TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_magazine_issue_supplement_oeuvre
    ON magazine_issue_supplement(oeuvre_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_magazine_issue_supplement_stored
    ON magazine_issue_supplement(stored_object_id);
