<?php
/**
 * @var list<array<string, mixed>> $films
 * @var string $filterSummary
 * @var string $sortSummary
 * @var bool $isGroupScope
 * @var array<int, list<array<string, mixed>>> $wishlistTargetsByFilmId
 */
?>
<article class="print-sheet">
    <h1 class="print-sheet__title"><?= $isGroupScope ? 'Envies du groupe' : 'Mes envies' ?></h1>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($filterSummary) ?>
        — tri : <?= Moncine\View::escape($sortSummary) ?>
    </p>

    <?php require MONCINE_ROOT . '/templates/_print_truncation_notice.php'; ?>

    <?php if ($films === []): ?>
        <p class="print-sheet__empty">Aucun titre à afficher avec les filtres actuels.</p>
    <?php elseif ($isGroupScope): ?>
        <?php require MONCINE_ROOT . '/templates/_print_wishlist_group_table.php'; ?>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_print_wishlist_table.php'; ?>
    <?php endif; ?>
</article>
