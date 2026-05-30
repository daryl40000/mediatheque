-- Demandes d’inscription (confirmation e-mail, puis approbation admin optionnelle).

CREATE TABLE IF NOT EXISTS inscription_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    nom TEXT NOT NULL DEFAULT '',
    prenom TEXT NOT NULL DEFAULT '',
    pseudo TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending_email'
        CHECK (status IN ('pending_email', 'pending_admin', 'approved', 'rejected')),
    confirm_token_hash TEXT NOT NULL DEFAULT '',
    confirm_expires_at TEXT NOT NULL,
    email_confirmed_at TEXT DEFAULT NULL,
    user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    reviewed_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    review_note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_inscription_requests_email_active
    ON inscription_requests(LOWER(TRIM(email)))
    WHERE status IN ('pending_email', 'pending_admin');

CREATE INDEX IF NOT EXISTS idx_inscription_requests_status
    ON inscription_requests(status);

INSERT OR IGNORE INTO app_metadata (key, value) VALUES ('registration_mode', 'disabled');
