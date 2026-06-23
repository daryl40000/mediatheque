<?php
/**
 * @var list<array<string, mixed>> $games
 * @var string $filterSummary
 * @var string $sortSummary
 * @var string $foyerLabel
 */
?>
<article class="print-sheet">
    <h1 class="print-sheet__title">Mes jeux</h1>
    <?php if ($foyerLabel !== ''): ?>
        <p class="print-sheet__meta">Collection partagée : <strong><?= Moncine\View::escape($foyerLabel) ?></strong></p>
    <?php endif; ?>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($filterSummary) ?>
        — tri : <?= Moncine\View::escape($sortSummary) ?>
    </p>

    <?php require MONCINE_ROOT . '/templates/_print_truncation_notice.php'; ?>

    <?php if ($games === []): ?>
        <p class="print-sheet__empty">Aucun jeu à afficher avec les filtres actuels.</p>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_print_games_table.php'; ?>
    <?php endif; ?>
</article>
