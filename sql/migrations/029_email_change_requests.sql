-- Changement d’adresse e-mail : confirmation sur la nouvelle adresse + notification de l’ancienne.

CREATE TABLE IF NOT EXISTS email_change_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    new_email TEXT NOT NULL,
    old_email TEXT NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_email_change_user_pending
    ON email_change_requests(user_id);

CREATE INDEX IF NOT EXISTS idx_email_change_token
    ON email_change_requests(token_hash);
