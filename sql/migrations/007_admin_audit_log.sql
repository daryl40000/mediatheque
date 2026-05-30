-- Phase 3 : journal des actions admin sur le catalogue.

CREATE TABLE IF NOT EXISTS catalog_admin_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id),
    action TEXT NOT NULL,
    oeuvre_id INTEGER DEFAULT NULL,
    details TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_catalog_admin_audit_created
    ON catalog_admin_audit(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_catalog_admin_audit_oeuvre
    ON catalog_admin_audit(oeuvre_id) WHERE oeuvre_id IS NOT NULL;
