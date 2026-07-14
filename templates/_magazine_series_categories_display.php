<?php
/**
 * Affichage des catégories héritées d’une série magazine.
 *
 * @var array<string, mixed>|null $series
 * @var list<string>|null $seriesCategories
 * @var string $labelPrefix
 */
$seriesCategories = $seriesCategories
    ?? ($series !== null ? Moncine\MagazineSeriesCategory::listForSeries($series) : []);
$labelPrefix = trim((string) ($labelPrefix ?? 'Catégories'));
if ($seriesCategories === []) {
    return;
}
?>
<p class="magazine-series-categories-display hint">
    <?= Moncine\View::escape($labelPrefix) ?> :
    <?php foreach ($seriesCategories as $categoryIndex => $categoryLabel): ?>
        <?php if ($categoryIndex > 0): ?><?php endif; ?>
        <span class="magazine-tag magazine-tag--series-category"><?= Moncine\View::escape($categoryLabel) ?></span>
    <?php endforeach; ?>
</p>
