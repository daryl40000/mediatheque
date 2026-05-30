-- Propositions d’œuvres au catalogue (validation administrateur).

CREATE TABLE IF NOT EXISTS catalogue_soumissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    payload_json TEXT NOT NULL,
    user_note TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'rejected')),
    resulting_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL,
    review_note TEXT NOT NULL DEFAULT '',
    reviewed_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    reviewed_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_catalogue_soumissions_status
    ON catalogue_soumissions(status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_catalogue_soumissions_user
    ON catalogue_soumissions(user_id, created_at DESC);
