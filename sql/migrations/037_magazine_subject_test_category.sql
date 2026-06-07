-- Phase M5 (v0.4.3) — Une seule catégorie « test » (fusion test_jeu / test_voiture / test_materiel)

UPDATE oeuvre_magazine_subject
SET subject_id = (
    SELECT MIN(ms_keep.id)
    FROM magazine_subject ms_keep
    INNER JOIN magazine_subject ms_old ON ms_old.id = oeuvre_magazine_subject.subject_id
    WHERE ms_keep.category IN ('test_jeu', 'test_voiture', 'test_materiel')
      AND ms_old.category IN ('test_jeu', 'test_voiture', 'test_materiel')
      AND LOWER(ms_keep.label) = LOWER(ms_old.label)
      AND LOWER(ms_keep.detail) = LOWER(ms_old.detail)
      AND ms_keep.parution_year = ms_old.parution_year
)
WHERE subject_id IN (
    SELECT id FROM magazine_subject
    WHERE category IN ('test_jeu', 'test_voiture', 'test_materiel')
);

DELETE FROM oeuvre_magazine_subject
WHERE rowid NOT IN (
    SELECT MIN(rowid)
    FROM oeuvre_magazine_subject
    GROUP BY oeuvre_id, subject_id
);

DELETE FROM magazine_subject
WHERE category IN ('test_jeu', 'test_voiture', 'test_materiel')
  AND id NOT IN (
    SELECT MIN(id)
    FROM magazine_subject
    WHERE category IN ('test_jeu', 'test_voiture', 'test_materiel')
    GROUP BY LOWER(label), LOWER(detail), parution_year
  );

UPDATE magazine_subject
SET category = 'test'
WHERE category IN ('test_jeu', 'test_voiture', 'test_materiel');
