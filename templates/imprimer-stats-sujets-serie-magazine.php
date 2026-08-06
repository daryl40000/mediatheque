<?php
/**
 * Liste imprimable des sujets d’une série (filtre catégorie + année).
 *
 * @var array<string, mixed> $series
 * @var list<array<string, mixed>> $rows
 * @var string $categoryLabel
 * @var int $year
 * @var bool $showScores
 * @var int $totalCount
 * @var string $publicationTypeLabel
 */
$seriesTitle = (string) ($series['titre'] ?? '');
$categoryLabel = (string) ($categoryLabel ?? '');
$year = (int) ($year ?? 0);
$showScores = !empty($showScores);
$totalCount = (int) ($totalCount ?? 0);
$rows = $rows ?? [];
?>
<article class="print-sheet">
    <h1 class="print-sheet__title"><?= Moncine\View::escape($seriesTitle) ?></h1>
    <p class="print-sheet__meta">
        <?= Moncine\View::escape($publicationTypeLabel ?? '') ?>
        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
            · <?= Moncine\View::escape((string) $series['editeur']) ?>
        <?php endif; ?>
    </p>
    <p class="print-sheet__meta">
        <strong><?= Moncine\View::escape($categoryLabel) ?></strong>
        — année <?= $year ?>
        — <?= $totalCount ?> article<?= $totalCount > 1 ? 's' : '' ?>
    </p>

    <?php if ($rows === []): ?>
        <p class="print-sheet__empty">Aucun article à afficher pour ce filtre.</p>
    <?php else: ?>
        <table class="print-table print-table--magazines">
            <thead>
                <tr>
                    <th class="col-narrow">N°</th>
                    <th>Titre</th>
                    <th class="col-narrow">Page</th>
                    <?php if ($showScores): ?>
                        <th class="col-narrow">Note</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="col-narrow"><?= Moncine\View::escape((string) ($row['issue_label'] ?? '')) ?></td>
                        <td><?= Moncine\View::escape((string) ($row['display_label'] ?? '')) ?></td>
                        <td class="col-narrow">
                            <?php
                            $page = (int) ($row['page'] ?? 0);
                            echo $page > 0 ? 'p.' . $page : '—';
                            ?>
                        </td>
                        <?php if ($showScores): ?>
                            <td class="col-narrow">
                                <?php
                                $scoreDisplay = trim((string) ($row['score_display'] ?? ''));
                                echo $scoreDisplay !== ''
                                    ? Moncine\View::escape($scoreDisplay)
                                    : '—';
                                ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</article>
