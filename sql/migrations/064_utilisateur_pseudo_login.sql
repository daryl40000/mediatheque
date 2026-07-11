-- Connexion par pseudo : unicité insensible à la casse (pseudos non vides uniquement).

CREATE UNIQUE INDEX IF NOT EXISTS idx_utilisateurs_pseudo_login
    ON utilisateurs(LOWER(TRIM(pseudo)))
    WHERE TRIM(pseudo) != '';
