<?php
/**
 * Affichage des catégories héritées d’une série magazine.
 *
 * @var array<string, mixed>|null $series
 * @var list<string>|null $seriesCategories
 * @var string $labelPrefix
 */
$labelPrefix = trim((string) ($labelPrefix ?? 'Catégories'));

// Toujours recalculer depuis $series quand elle est disponible (ex. boucle Mes magazines).
// Sinon $seriesCategories resterait figée sur la première tuile du foreach.
if (isset($series) && is_array($series)) {
    $seriesCategories = Moncine\MagazineSeriesCategory::listForSeries($series);
} elseif (!isset($seriesCategories)) {
    $seriesCategories = [];
}

if ($seriesCategories === []) {
    return;
}

$showLabel = trim($labelPrefix) !== '';
?>
<p class="magazine-series-categories-display hint">
    <?php if ($showLabel): ?><?= Moncine\View::escape($labelPrefix) ?> : <?php endif; ?>
    <?php foreach ($seriesCategories as $categoryIndex => $categoryLabel): ?>
        <?php if ($categoryIndex > 0): ?><?php endif; ?>
        <span class="magazine-tag magazine-tag--series-category"><?= Moncine\View::escape($categoryLabel) ?></span>
    <?php endforeach; ?>
</p>
