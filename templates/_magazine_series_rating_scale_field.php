<?php
/**
 * Champ « Notes sur combien ? » + périodes d’échelle par plage de numéros.
 *
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>>|null $ratingPeriods
 */
$currentRatingScale = Moncine\MagazineRatingScale::normalize($series['rating_scale'] ?? null);
$currentRatingMax = $currentRatingScale !== null
    ? (int) Moncine\MagazineRatingScale::maxValue($currentRatingScale)
    : null;
$ratingPeriods = $ratingPeriods ?? [];
$maxScale = (int) Moncine\MagazineRatingScale::MAX_ALLOWED;

// Au moins une ligne vide pour commencer à saisir.
if ($ratingPeriods === []) {
    $ratingPeriods = [
        [
            'from_numero_ordre' => '',
            'to_numero_ordre' => '',
            'rating_scale' => '',
        ],
    ];
}
?>
<label for="rating_scale">Notes sur combien ? (par défaut)</label>
<input type="number"
       name="rating_scale"
       id="rating_scale"
       min="1"
       max="<?= $maxScale ?>"
       step="1"
       inputmode="numeric"
       placeholder="Ex. 5, 10, 20, 100…"
       value="<?= $currentRatingMax !== null ? (string) $currentRatingMax : '' ?>">
<p class="hint">
    Échelle utilisée pour les notes des <strong>tests</strong> quand aucune période ci-dessous
    ne correspond au numéro (laisser vide = pas de notation hors période).
    Exemples : 5 ou 6 (souvent affiché en étoiles), 10, 20, 50, 100 (pourcentages).
    Les notes sont aussi converties sur 100 pour les moyennes.
</p>

<div class="magazine-rating-periods" data-rating-periods>
    <span class="magazine-rating-periods__label" id="rating_periods_label">
        Périodes d’échelle (optionnel)
    </span>
    <p class="hint">
        Si la revue a changé de barème au fil du temps, indiquez les plages de numéros.
        Exemple : du 1 au 92 → sur 5 ; du 93 au 110 → sur 100.
        Laissez « Au n° » vide pour « jusqu’à la fin ». Les plages ne doivent pas se chevaucher.
    </p>

    <div class="magazine-rating-periods__rows" role="group" aria-labelledby="rating_periods_label">
        <?php foreach ($ratingPeriods as $period): ?>
            <?php
            $fromVal = $period['from_numero_ordre'] ?? '';
            $toVal = $period['to_numero_ordre'] ?? '';
            $scaleVal = $period['rating_scale'] ?? '';
            if ($fromVal !== '' && is_numeric($fromVal)) {
                $fromVal = Moncine\MagazineRatingScale::formatNumber((float) $fromVal);
            }
            if ($toVal !== '' && $toVal !== null && is_numeric($toVal)) {
                $toVal = Moncine\MagazineRatingScale::formatNumber((float) $toVal);
            } else {
                $toVal = '';
            }
            $scaleNorm = Moncine\MagazineRatingScale::normalize($scaleVal);
            $scaleVal = $scaleNorm !== null
                ? (string) (int) Moncine\MagazineRatingScale::maxValue($scaleNorm)
                : '';
            ?>
            <div class="magazine-rating-periods__row" data-rating-period-row>
                <label class="magazine-rating-periods__field">
                    <span class="visually-hidden">Du numéro</span>
                    <span class="magazine-rating-periods__caption" aria-hidden="true">Du n°</span>
                    <input type="number"
                           name="rating_period_from[]"
                           min="0"
                           step="0.5"
                           inputmode="decimal"
                           placeholder="1"
                           value="<?= Moncine\View::escape((string) $fromVal) ?>">
                </label>
                <label class="magazine-rating-periods__field">
                    <span class="visually-hidden">Au numéro</span>
                    <span class="magazine-rating-periods__caption" aria-hidden="true">Au n°</span>
                    <input type="number"
                           name="rating_period_to[]"
                           min="0"
                           step="0.5"
                           inputmode="decimal"
                           placeholder="fin"
                           value="<?= Moncine\View::escape((string) $toVal) ?>">
                </label>
                <label class="magazine-rating-periods__field magazine-rating-periods__field--scale">
                    <span class="visually-hidden">Notes sur</span>
                    <span class="magazine-rating-periods__caption" aria-hidden="true">Notes sur</span>
                    <input type="number"
                           name="rating_period_scale[]"
                           min="1"
                           max="<?= $maxScale ?>"
                           step="1"
                           inputmode="numeric"
                           placeholder="5"
                           value="<?= Moncine\View::escape((string) $scaleVal) ?>">
                </label>
                <button type="button"
                        class="btn btn-secondary btn-sm magazine-rating-periods__remove"
                        title="Retirer cette période"
                        aria-label="Retirer cette période">×</button>
            </div>
        <?php endforeach; ?>
    </div>

    <p>
        <button type="button" class="btn btn-secondary btn-sm" data-rating-period-add>
            Ajouter une période
        </button>
    </p>
</div>
