<?php
/**
 * @var list<array<string, mixed>> $seriesList
 * @var string $filterSummary
 * @var string $sortSummary
 * @var string $foyerLabel
 * @var string $countColumnLabel
 * @var bool $isWishlist
 */
$seriesList = $seriesList ?? [];
$foyerLabel = (string) ($foyerLabel ?? '');
$countColumnLabel = (string) ($countColumnLabel ?? 'Possédés / catalogue');
$isWishlist = !empty($isWishlist);
$domain = 'magazine';
?>
<article class="print-sheet">
    <h1 class="print-sheet__title"><?= $isWishlist ? 'Mes envies magazines' : 'Mes magazines' ?></h1>
    <?php if ($foyerLabel !== ''): ?>
        <p class="print-sheet__meta">Collection partagée : <strong><?= Moncine\View::escape($foyerLabel) ?></strong></p>
    <?php endif; ?>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape((string) ($filterSummary ?? '')) ?>
        — tri : <?= Moncine\View::escape((string) ($sortSummary ?? '')) ?>
    </p>

    <?php require MONCINE_ROOT . '/templates/_print_truncation_notice.php'; ?>

    <?php if ($seriesList === []): ?>
        <p class="print-sheet__empty">Aucune série à afficher avec les filtres actuels.</p>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_print_series_table.php'; ?>
    <?php endif; ?>
</article>
