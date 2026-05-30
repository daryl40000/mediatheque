<?php
/**
 * @var list<array<string, mixed>> $films
 * @var string $filterSummary
 * @var string $sortSummary
 * @var string $foyerLabel
 */
?>
<article class="print-sheet">
    <h1 class="print-sheet__title">Mes films</h1>
    <?php if ($foyerLabel !== ''): ?>
        <p class="print-sheet__meta">Collection partagée : <strong><?= Moncine\View::escape($foyerLabel) ?></strong></p>
    <?php endif; ?>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($filterSummary) ?>
        — tri : <?= Moncine\View::escape($sortSummary) ?>
    </p>

    <?php require MONCINE_ROOT . '/templates/_print_truncation_notice.php'; ?>

    <?php if ($films === []): ?>
        <p class="print-sheet__empty">Aucun film à afficher avec les filtres actuels.</p>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_print_collection_table.php'; ?>
    <?php endif; ?>
</article>
