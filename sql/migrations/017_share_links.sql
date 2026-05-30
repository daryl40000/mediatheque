-- Phase 7 : liens de partage lecture seule (collection foyer / wishlist personnelle).

CREATE TABLE IF NOT EXISTS share_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    scope TEXT NOT NULL CHECK (scope IN ('collection', 'wishlist')),
    foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT DEFAULT NULL,
    revoked_at TEXT DEFAULT NULL,
    last_access_at TEXT DEFAULT NULL,
    access_count INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_share_links_user ON share_links(user_id);
CREATE INDEX IF NOT EXISTS idx_share_links_foyer ON share_links(foyer_id) WHERE foyer_id IS NOT NULL;
