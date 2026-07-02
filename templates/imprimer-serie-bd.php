<?php
/**
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>> $rows
 * @var string $filterSummary
 * @var string $sortSummary
 * @var string $kindLabel
 * @var int $totalCount
 * @var bool $truncated
 * @var int $maxRows
 */
$seriesTitle = (string) ($series['titre'] ?? 'Série');
?>
<article class="print-sheet">
    <h1 class="print-sheet__title"><?= Moncine\View::escape($seriesTitle) ?></h1>
    <?php if ($series !== null): ?>
        <p class="print-sheet__meta">
            <?= Moncine\View::escape($kindLabel) ?>
            <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                · <?= Moncine\View::escape((string) $series['editeur']) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($filterSummary) ?>
        — tri : <?= Moncine\View::escape($sortSummary) ?>
        — <?= (int) $totalCount ?> tome<?= $totalCount > 1 ? 's' : '' ?>
    </p>

    <p class="print-sheet__legend">
        <strong>Possession :</strong>
        <span class="magazine-possession--none">Non possédé</span> ·
        <span class="magazine-possession--owned">Possédé</span> (album, relié, poche…)
    </p>

    <?php if ($truncated): ?>
        <p class="print-sheet__warn alert alert-warning">
            Liste tronquée aux <?= (int) $maxRows ?> premiers tomes (<?= (int) $totalCount ?> au total).
        </p>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="print-sheet__empty">Aucun tome à afficher avec les filtres actuels.</p>
    <?php else: ?>
        <table class="print-table print-table--magazines">
            <thead>
                <tr>
                    <th class="col-narrow">Tome</th>
                    <th>Titre</th>
                    <th class="col-narrow">Année</th>
                    <th>Possession</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="col-narrow">
                            <?php if (!empty($row['est_hors_serie'])): ?>
                                <span class="badge">HS</span>
                            <?php endif; ?>
                            <?php
                            $label = trim((string) ($row['tome_label'] ?? ''));
                            $num = (int) ($row['tome_numero'] ?? 0);
                            echo Moncine\View::escape($label !== '' ? $label : (string) $num);
                            ?>
                        </td>
                        <td><?= Moncine\View::escape((string) ($row['display_titre'] ?? '')) ?></td>
                        <td class="col-narrow"><?= (int) ($row['annee'] ?? 0) > 0 ? (int) $row['annee'] : '—' ?></td>
                        <td class="<?= Moncine\View::escape((string) ($row['possession_class'] ?? '')) ?>">
                            <?= Moncine\View::escape((string) ($row['possession_label'] ?? '')) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</article>
