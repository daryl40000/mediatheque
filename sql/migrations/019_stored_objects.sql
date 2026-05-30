-- Phase 9 — Stockage local des fichiers volumineux (racine MONCINE_MEDIA_PATH)

CREATE TABLE IF NOT EXISTS stored_objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    backend TEXT NOT NULL DEFAULT 'local' CHECK (backend IN ('local')),
    relative_path TEXT NOT NULL,
    mime TEXT NOT NULL DEFAULT 'application/octet-stream',
    size_bytes INTEGER NOT NULL DEFAULT 0,
    checksum TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_stored_objects_path ON stored_objects(relative_path);
CREATE INDEX IF NOT EXISTS idx_stored_objects_backend ON stored_objects(backend);
