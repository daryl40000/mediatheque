-- Table d’équivalence étoiles entières → % pour les moyennes (séries notées < 10).
-- JSON ex. {"0":15,"1":40,"2":60,"3":75,"4":90,"5":100} ; NULL = règle de trois.

ALTER TABLE series ADD COLUMN star_percent_map TEXT DEFAULT NULL;
