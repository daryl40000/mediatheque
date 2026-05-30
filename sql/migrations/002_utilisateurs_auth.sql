-- Comptes : e-mail, mot de passe, rôle (paquet 2.0).

ALTER TABLE utilisateurs ADD COLUMN email TEXT NOT NULL DEFAULT '';
ALTER TABLE utilisateurs ADD COLUMN password_hash TEXT NOT NULL DEFAULT '';
ALTER TABLE utilisateurs ADD COLUMN role TEXT NOT NULL DEFAULT 'user';
ALTER TABLE utilisateurs ADD COLUMN actif INTEGER NOT NULL DEFAULT 1;
ALTER TABLE utilisateurs ADD COLUMN last_login_at TEXT DEFAULT NULL;

-- L’ancien utilisateur technique devient admin jusqu’à configuration du mot de passe.
UPDATE utilisateurs SET role = 'admin' WHERE id = 1;

CREATE UNIQUE INDEX IF NOT EXISTS idx_utilisateurs_email
    ON utilisateurs(email) WHERE email != '';
