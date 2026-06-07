-- Phase M5 (v0.4.1) — Plateformes par série ; année de parution sur les sujets

ALTER TABLE series ADD COLUMN platforms TEXT NOT NULL DEFAULT '';

ALTER TABLE magazine_subject ADD COLUMN parution_year INTEGER NOT NULL DEFAULT 0;

DROP INDEX IF EXISTS idx_magazine_subject_unique;

CREATE UNIQUE INDEX IF NOT EXISTS idx_magazine_subject_unique
    ON magazine_subject(category, label COLLATE NOCASE, detail COLLATE NOCASE, parution_year);

UPDATE magazine_subject
SET parution_year = (
    SELECT CAST(strftime('%Y', om.date_parution) AS INTEGER)
    FROM oeuvre_magazine_subject oms
    INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
    WHERE oms.subject_id = magazine_subject.id
      AND om.date_parution IS NOT NULL
      AND TRIM(om.date_parution) != ''
    ORDER BY om.date_parution DESC
    LIMIT 1
)
WHERE parution_year = 0;
