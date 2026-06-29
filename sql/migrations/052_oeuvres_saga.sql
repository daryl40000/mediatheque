-- Sagas films au niveau catalogue (comme oeuvre_jeu.franchise pour les jeux).
ALTER TABLE oeuvres ADD COLUMN saga TEXT NOT NULL DEFAULT '';
ALTER TABLE oeuvres ADD COLUMN saga_ordre INTEGER NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_oeuvres_saga ON oeuvres(saga COLLATE NOCASE) WHERE saga != '';

-- Reprendre les sagas déjà saisies sur des exemplaires bibliothèque.
UPDATE oeuvres
SET saga = (
        SELECT b.saga
        FROM bibliotheque b
        WHERE b.oeuvre_id = oeuvres.id
          AND TRIM(b.saga) != ''
        ORDER BY b.saga_ordre DESC, b.id ASC
        LIMIT 1
    ),
    saga_ordre = COALESCE((
        SELECT b.saga_ordre
        FROM bibliotheque b
        WHERE b.oeuvre_id = oeuvres.id
          AND TRIM(b.saga) != ''
        ORDER BY b.saga_ordre DESC, b.id ASC
        LIMIT 1
    ), 0)
WHERE id IN (
    SELECT DISTINCT oeuvre_id
    FROM bibliotheque
    WHERE TRIM(saga) != ''
);
