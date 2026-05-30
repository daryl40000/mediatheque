-- Phase 2 : format image / son = données de l'exemplaire personnel (bibliotheque), pas du catalogue.

ALTER TABLE bibliotheque ADD COLUMN format_image TEXT DEFAULT '';
ALTER TABLE bibliotheque ADD COLUMN format_son TEXT DEFAULT '';

UPDATE bibliotheque
SET format_image = COALESCE(
        (SELECT o.format_image FROM oeuvres o WHERE o.id = bibliotheque.oeuvre_id),
        ''
    ),
    format_son = COALESCE(
        (SELECT o.format_son FROM oeuvres o WHERE o.id = bibliotheque.oeuvre_id),
        ''
    )
WHERE TRIM(COALESCE(format_image, '')) = ''
  AND TRIM(COALESCE(format_son, '')) = '';
