<?php
/**
 * Champ « Notes sur combien ? » pour une série magazine (plafond libre).
 *
 * @var array<string, mixed>|null $series
 */
$currentRatingScale = Moncine\MagazineRatingScale::normalize($series['rating_scale'] ?? null);
$currentRatingMax = $currentRatingScale !== null
    ? (int) Moncine\MagazineRatingScale::maxValue($currentRatingScale)
    : null;
?>
<label for="rating_scale">Notes sur combien ?</label>
<input type="number"
       name="rating_scale"
       id="rating_scale"
       min="1"
       max="<?= (int) Moncine\MagazineRatingScale::MAX_ALLOWED ?>"
       step="1"
       inputmode="numeric"
       placeholder="Ex. 5, 10, 20, 100…"
       value="<?= $currentRatingMax !== null ? (string) $currentRatingMax : '' ?>">
<p class="hint">
    Plafond utilisé pour les notes des <strong>tests</strong> (laisser vide = pas de notation).
    Exemples : 5 ou 6 (souvent affiché en étoiles), 10, 20, 50, 100 (pourcentages).
    Les notes sont aussi converties sur 100 pour les moyennes.
</p>
