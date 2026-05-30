<?php
/**
 * @var bool $printTruncated
 * @var int $printTotalRows
 * @var int $printRowLimit
 */
if (!empty($printTruncated)): ?>
    <p class="print-sheet__warn alert alert-warning">
        Liste limitée aux <?= (int) $printRowLimit ?> premières lignes
        (<?= (int) $printTotalRows ?> au total avec les filtres actuels).
        Affinez la recherche ou les filtres pour une liste plus courte.
    </p>
<?php endif; ?>
