-- Phase 8 — Prêts : demandes et réservation avant prêt effectif.

CREATE TABLE IF NOT EXISTS loan_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    owner_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    requester_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'declined', 'canceled', 'lent')),
    requested_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL,
    lent_at TEXT DEFAULT NULL,
    loan_id INTEGER DEFAULT NULL REFERENCES loans(id) ON DELETE SET NULL,
    note TEXT NOT NULL DEFAULT ''
);

-- Un ami ne peut pas demander deux fois la même entrée en même temps.
CREATE UNIQUE INDEX IF NOT EXISTS idx_loan_requests_unique_active
    ON loan_requests(bibliotheque_id, requester_user_id)
    WHERE status IN ('pending', 'accepted');

CREATE INDEX IF NOT EXISTS idx_loan_requests_owner_status
    ON loan_requests(owner_user_id, status, requested_at DESC);

CREATE INDEX IF NOT EXISTS idx_loan_requests_requester_status
    ON loan_requests(requester_user_id, status, requested_at DESC);

