-- Exemplaire marqué comme non prêtable (jeux physiques, films…).
ALTER TABLE bibliotheque ADD COLUMN non_pretable INTEGER NOT NULL DEFAULT 0;
