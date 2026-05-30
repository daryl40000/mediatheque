-- Sagas / suites de films (nom + numéro d’ordre dans la saga).
ALTER TABLE films ADD COLUMN saga TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN saga_ordre INTEGER DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_films_saga ON films(saga) WHERE TRIM(saga) != '';
