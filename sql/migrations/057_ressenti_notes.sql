-- Migration 057 : notes 1–10 → ressenti 1–5 (J'adore … Je déteste).

UPDATE historique SET note = 1 WHERE note BETWEEN 1 AND 2;
UPDATE historique SET note = 2 WHERE note BETWEEN 3 AND 4;
UPDATE historique SET note = 3 WHERE note BETWEEN 5 AND 6;
UPDATE historique SET note = 4 WHERE note BETWEEN 7 AND 8;
UPDATE historique SET note = 5 WHERE note BETWEEN 9 AND 10;
