-- Jeux multi-plateformes : catalogue (platforms) + exemplaire utilisateur (owned_platforms).
ALTER TABLE oeuvre_jeu ADD COLUMN platforms TEXT NOT NULL DEFAULT '';
ALTER TABLE bibliotheque ADD COLUMN owned_platforms TEXT NOT NULL DEFAULT '';

UPDATE oeuvre_jeu
SET platforms = TRIM(platform)
WHERE TRIM(platform) != '' AND TRIM(COALESCE(platforms, '')) = '';

UPDATE bibliotheque
SET owned_platforms = (
    SELECT TRIM(oj.platforms)
    FROM oeuvre_jeu oj
    INNER JOIN oeuvres o ON o.id = oj.oeuvre_id
    WHERE oj.oeuvre_id = bibliotheque.oeuvre_id
      AND o.media_domain = 'jeu'
      AND TRIM(oj.platforms) != ''
    LIMIT 1
)
WHERE TRIM(COALESCE(owned_platforms, '')) = ''
  AND EXISTS (
    SELECT 1 FROM oeuvre_jeu oj2
    INNER JOIN oeuvres o2 ON o2.id = oj2.oeuvre_id
    WHERE oj2.oeuvre_id = bibliotheque.oeuvre_id
      AND o2.media_domain = 'jeu'
      AND TRIM(oj2.platforms) != ''
  );

UPDATE bibliotheque
SET owned_platforms = (
    SELECT TRIM(oj.platform)
    FROM oeuvre_jeu oj
    INNER JOIN oeuvres o ON o.id = oj.oeuvre_id
    WHERE oj.oeuvre_id = bibliotheque.oeuvre_id
      AND o.media_domain = 'jeu'
      AND TRIM(oj.platform) != ''
    LIMIT 1
)
WHERE TRIM(COALESCE(owned_platforms, '')) = ''
  AND EXISTS (
    SELECT 1 FROM oeuvre_jeu oj
    INNER JOIN oeuvres o ON o.id = oj.oeuvre_id
    WHERE oj.oeuvre_id = bibliotheque.oeuvre_id
      AND o.media_domain = 'jeu'
      AND TRIM(oj.platform) != ''
  );
