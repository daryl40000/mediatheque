-- URL canonique de la fiche édition sur DVDFr.com (fournie par l’API dvd.php).

ALTER TABLE bibliotheque ADD COLUMN dvdfr_url TEXT DEFAULT '';
ALTER TABLE films ADD COLUMN dvdfr_url TEXT DEFAULT '';
