-- Index email_change_requests : retirer datetime('now') non déterministe (SQLite récent).

DROP INDEX IF EXISTS idx_email_change_user_pending;

CREATE UNIQUE INDEX IF NOT EXISTS idx_email_change_user_pending
    ON email_change_requests(user_id);
