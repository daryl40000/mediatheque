-- Phase 8 — Prêts : suivi des exemplaires prêtés.

CREATE TABLE IF NOT EXISTS loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    lender_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    borrower_user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    borrower_name TEXT NOT NULL DEFAULT '',
    loaned_at TEXT NOT NULL DEFAULT (date('now')),
    due_at TEXT DEFAULT NULL,
    returned_at TEXT DEFAULT NULL,
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_loans_bibliotheque_active
    ON loans(bibliotheque_id)
    WHERE returned_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_loans_lender_active
    ON loans(lender_user_id, returned_at)
    WHERE returned_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_loans_borrower_active
    ON loans(borrower_user_id, returned_at)
    WHERE borrower_user_id IS NOT NULL AND returned_at IS NULL;

