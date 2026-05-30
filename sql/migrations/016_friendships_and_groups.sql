-- Phase 6 : amis, groupes famille (foyers utilisateurs), invitations.

CREATE TABLE IF NOT EXISTS friendships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requester_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    addressee_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'blocked')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL,
    UNIQUE (requester_id, addressee_id)
);

CREATE INDEX IF NOT EXISTS idx_friendships_addressee_status
    ON friendships(addressee_id, status);

CREATE INDEX IF NOT EXISTS idx_friendships_requester_status
    ON friendships(requester_id, status);

ALTER TABLE foyers ADD COLUMN kind TEXT NOT NULL DEFAULT 'famille';
ALTER TABLE foyers ADD COLUMN created_by_user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id);

CREATE TABLE IF NOT EXISTS group_members (
    foyer_id INTEGER NOT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('founder', 'member')),
    joined_at TEXT NOT NULL DEFAULT (datetime('now')),
    invited_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id),
    PRIMARY KEY (foyer_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_group_members_user ON group_members(user_id);

CREATE TABLE IF NOT EXISTS group_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foyer_id INTEGER NOT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    invited_by INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'declined')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_group_invitations_user_status
    ON group_invitations(user_id, status);
