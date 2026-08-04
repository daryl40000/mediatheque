<?php
/**
 * Pastille « moyenne presse » (notes magazines converties /100), à droite du titre.
 *
 * @var float|null $magazinePressAverage
 * @var int $magazinePressScoreCount
 * @var string $class classes CSS supplémentaires
 */
$magazinePressAverage = isset($magazinePressAverage) && is_numeric($magazinePressAverage)
    ? (float) $magazinePressAverage
    : null;
$magazinePressScoreCount = (int) ($magazinePressScoreCount ?? 0);
if ($magazinePressAverage === null || $magazinePressScoreCount <= 0) {
    return;
}
$class = trim((string) ($class ?? ''));
$extraClass = $class !== '' ? ' ' . $class : '';
?>
<p class="game-detail__press-average<?= Moncine\View::escape($extraClass) ?>"
   aria-label="Note moyenne presse">
    <span class="game-detail__press-average__value">
        <?= Moncine\View::escape(Moncine\MagazineRatingScale::formatNumber($magazinePressAverage)) ?>/100
    </span>
    <span class="game-detail__press-average__label">
        presse
        (<?= $magazinePressScoreCount ?> test<?= $magazinePressScoreCount > 1 ? 's' : '' ?>)
    </span>
</p>
