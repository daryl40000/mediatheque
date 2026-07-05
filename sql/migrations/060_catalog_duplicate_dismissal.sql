-- Doublons catalogue signalés puis validés comme légitimes (ex. même titre, années différentes).

CREATE TABLE IF NOT EXISTS catalog_duplicate_dismissal (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_type TEXT NOT NULL,
    group_key TEXT NOT NULL,
    dismissed_by_user_id INTEGER NOT NULL DEFAULT 0,
    dismissed_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(group_type, group_key)
);

CREATE INDEX IF NOT EXISTS idx_catalog_duplicate_dismissal_type
    ON catalog_duplicate_dismissal(group_type, group_key);
