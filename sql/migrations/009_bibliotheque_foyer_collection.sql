-- Phase 4 : foyer_id sur la collection (exemplaires partagés).

ALTER TABLE bibliotheque ADD COLUMN foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id);

CREATE INDEX IF NOT EXISTS idx_bibliotheque_foyer_statut ON bibliotheque(foyer_id, statut);
