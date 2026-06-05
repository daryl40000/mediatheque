<?php
/**
 * @var array<string, mixed> $series
 * @var list<array<string, mixed>> $rows
 * @var string $filterSummary
 * @var string $sortSummary
 * @var string $publicationTypeLabel
 * @var int $totalCount
 * @var bool $truncated
 * @var int $maxRows
 */
$seriesTitle = (string) ($series['titre'] ?? '');
?>
<article class="print-sheet">
    <h1 class="print-sheet__title"><?= Moncine\View::escape($seriesTitle) ?></h1>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($publicationTypeLabel) ?>
        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
            · <?= Moncine\View::escape((string) $series['editeur']) ?>
        <?php endif; ?>
    </p>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($filterSummary) ?>
        — tri : <?= Moncine\View::escape($sortSummary) ?>
        — <?= (int) $totalCount ?> numéro<?= $totalCount > 1 ? 's' : '' ?>
    </p>

    <p class="print-sheet__legend">
        <strong>Possession :</strong>
        <span class="magazine-possession--none">Non possédé</span> ·
        <span class="magazine-possession--owned">Papier</span> ou <span class="magazine-possession--owned">PDF</span> ·
        <span class="magazine-possession--both">Papier + PDF</span>
    </p>

    <?php if ($truncated): ?>
        <p class="print-sheet__warn alert alert-warning">
            Liste tronquée aux <?= (int) $maxRows ?> premiers numéros (<?= (int) $totalCount ?> au total).
            Affinez la recherche ou les filtres sur la page série si besoin.
        </p>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="print-sheet__empty">Aucun numéro à afficher avec les filtres actuels.</p>
    <?php else: ?>
        <table class="print-table print-table--magazines">
            <thead>
                <tr>
                    <th class="col-narrow">N°</th>
                    <th class="col-narrow">Date</th>
                    <th class="col-narrow">Pages</th>
                    <th>Possession</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="col-narrow">
                            <?php if (!empty($row['est_hors_serie'])): ?>
                                <span class="print-table__hs">HS</span>
                            <?php endif; ?>
                            <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                        </td>
                        <td class="col-narrow"><?= Moncine\View::escape((string) ($row['date_label'] ?? '')) ?></td>
                        <td class="col-narrow"><?= (int) ($row['pages'] ?? 0) > 0 ? (int) $row['pages'] : '—' ?></td>
                        <td class="<?= Moncine\View::escape((string) ($row['possession_class'] ?? '')) ?>">
                            <?= Moncine\View::escape((string) ($row['possession_label'] ?? '')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</article>
