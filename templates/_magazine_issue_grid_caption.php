<?php
/**
 * Infos d’un numéro magazine dans la bulle au survol.
 *
 * @var array<string, mixed> $row
 * @var array<string, mixed>|null $series
 * @var bool $showSeriesTitle
 * @var bool $isWishlist
 */
$row = $row ?? [];
$series = $series ?? null;
$showSeriesTitle = (bool) ($showSeriesTitle ?? false);
$isWishlist = (bool) ($isWishlist ?? false);

$dateLabel = Moncine\PublicationType::formatParutionDate(
    (string) ($row['date_parution'] ?? ''),
    (string) ($row['publication_type'] ?? ($series['publication_type'] ?? ''))
);
$pages = (int) ($row['pages'] ?? 0);
$isPossessed = Moncine\MagazineSupport::isPossessed($row);
$issue = $row;
?>
<div class="collection-grid__caption">
    <?php if ($showSeriesTitle): ?>
        <p class="collection-grid__meta magazine-grid-caption__series">
            <?= Moncine\View::escape((string) ($row['series_titre'] ?? '')) ?>
        </p>
    <?php endif; ?>
    <h3 class="collection-grid__title">
        <?php if (!empty($row['est_hors_serie'])): ?>
            <span class="badge">HS</span>
        <?php endif; ?>
        N° <?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
    </h3>
    <p class="collection-grid__meta">
        <?= Moncine\View::escape($dateLabel) ?>
        <?php if ($pages > 0): ?>
            · <?= $pages ?> p.
        <?php endif; ?>
    </p>
    <p class="collection-grid__meta magazine-grid-caption__tags">
        <?php require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
        <?php if (!$isWishlist && !$isPossessed): ?>
            <span class="magazine-tag magazine-tag--none">Non possédé</span>
        <?php endif; ?>
    </p>
    <?php if ($series !== null): ?>
        <?php
        $labelPrefix = '';
        require MONCINE_ROOT . '/templates/_magazine_series_categories_display.php';
        ?>
    <?php endif; ?>
    <?php if (!$isWishlist): ?>
        <div class="magazine-grid-caption__actions">
            <?php require MONCINE_ROOT . '/templates/_magazine_wishlist_button.php'; ?>
        </div>
    <?php endif; ?>
</div>
