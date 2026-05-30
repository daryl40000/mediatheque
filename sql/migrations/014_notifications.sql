-- Notifications in-app (soumissions catalogue, etc.).

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    link_url TEXT NOT NULL DEFAULT '',
    related_submission_id INTEGER DEFAULT NULL,
    related_oeuvre_id INTEGER DEFAULT NULL,
    read_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_created
    ON notifications(user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
    ON notifications(user_id)
    WHERE read_at IS NULL;
