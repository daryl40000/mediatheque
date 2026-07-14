<?php
/**
 * Infos d’une série magazine dans la bulle au survol (Mes magazines).
 *
 * @var array<string, mixed> $series
 */
$seriesTitle = (string) ($series['titre'] ?? '');
$issueCount = (int) ($series['issue_count'] ?? 0);
$publicationLabel = Moncine\PublicationType::label((string) ($series['publication_type'] ?? ''));
$editeur = trim((string) ($series['editeur'] ?? ''));
$seriesCategories = Moncine\MagazineSeriesCategory::listForSeries($series);
?>
<div class="collection-grid__caption">
    <h3 class="collection-grid__title"><?= Moncine\View::escape($seriesTitle) ?></h3>
    <p class="collection-grid__meta">
        <?= Moncine\View::escape($publicationLabel) ?>
        · <?= $issueCount ?> numéro<?= $issueCount > 1 ? 's' : '' ?> possédé<?= $issueCount > 1 ? 's' : '' ?>
        <?php if ($issueCount === 0): ?>
            — ajoutez le premier
        <?php endif; ?>
    </p>
    <?php if ($editeur !== ''): ?>
        <p class="collection-grid__meta"><?= Moncine\View::escape($editeur) ?></p>
    <?php endif; ?>
    <?php if ($seriesCategories !== []): ?>
        <p class="collection-grid__meta magazine-grid-caption__tags">
            <?php foreach ($seriesCategories as $categoryLabel): ?>
                <span class="magazine-tag magazine-tag--series-category"><?= Moncine\View::escape($categoryLabel) ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</div>
