<?php
/**
 * Pagination Mes films (liste ou vignettes).
 *
 * @var int $page
 * @var int $totalPages
 * @var int $listTotal
 * @var int $perPage
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $kindFilter
 * @var string $viewMode
 * @var string $paginationIdSuffix
 */
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$listTotal = (int) ($listTotal ?? 0);
$perPage = (int) ($perPage ?? 0);
$paginationIdSuffix = (string) ($paginationIdSuffix ?? '');

if ($totalPages <= 1) {
    return;
}

$pageLink = static function (int $targetPage) use (
    $query,
    $sortBy,
    $sortDir,
    $kindFilter,
    $viewMode,
    $totalPages
): string {
    $targetPage = max(1, min($targetPage, $totalPages));

    return Moncine\View::filmsCollectionUrl(
        $query,
        $sortBy,
        $sortDir,
        $kindFilter,
        $viewMode,
        $targetPage
    ) . '#films-collection';
};

$gotoPageId = 'films_goto_page' . $paginationIdSuffix;
$perPageLabel = Moncine\CollectionViewMode::isGrid($viewMode)
    ? Moncine\FilmCollectionPagination::GRID_ROWS . ' lignes × ' . Moncine\FilmCollectionPagination::GRID_COLUMNS . ' colonnes'
    : (string) Moncine\FilmCollectionPagination::LIST_PER_PAGE . ' films';
?>
<nav class="list-pager films-collection-pager" aria-label="Pagination de la collection">
    <span class="list-pager__status">
        Page <?= $page ?> / <?= $totalPages ?>
        <?php if ($listTotal > 0): ?>
            <span class="list-pager__status-meta">
                (<?= $listTotal ?> film<?= $listTotal > 1 ? 's' : '' ?>
                <?php if ($perPage > 0): ?>
                    · <?= Moncine\View::escape($perPageLabel) ?> par page
                <?php endif; ?>)
            </span>
        <?php endif; ?>
    </span>

    <div class="list-pager__group">
        <?php if ($page > 1): ?>
            <a href="<?= Moncine\View::escape($pageLink(1)) ?>" class="list-pager__link">Première</a>
            <span class="list-pager__sep" aria-hidden="true">·</span>
            <a href="<?= Moncine\View::escape($pageLink($page - 1)) ?>" class="list-pager__link">← Préc.</a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <?php if ($page > 1): ?>
                <span class="list-pager__sep" aria-hidden="true">·</span>
            <?php endif; ?>
            <a href="<?= Moncine\View::escape($pageLink($page + 1)) ?>" class="list-pager__link">Suiv. →</a>
            <span class="list-pager__sep" aria-hidden="true">·</span>
            <a href="<?= Moncine\View::escape($pageLink($totalPages)) ?>" class="list-pager__link">Dernière</a>
        <?php endif; ?>
    </div>

    <form method="get" action="/films.php#films-collection" class="list-pager__goto films-collection-pager__goto">
        <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy ?? 'titre') ?>">
        <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir ?? 'asc') ?>">
        <?php if (trim((string) ($query ?? '')) !== ''): ?>
            <input type="hidden" name="q" value="<?= Moncine\View::escape((string) $query) ?>">
        <?php endif; ?>
        <?php if (($kindFilter ?? Moncine\ContentKindFilter::ALL) !== Moncine\ContentKindFilter::ALL): ?>
            <input type="hidden" name="kind" value="<?= Moncine\View::escape((string) $kindFilter) ?>">
        <?php endif; ?>
        <?php
        $paginationView = Moncine\CollectionViewMode::queryValue($viewMode ?? '');
        if ($paginationView !== null):
            ?>
            <input type="hidden" name="view" value="<?= Moncine\View::escape($paginationView) ?>">
        <?php endif; ?>
        <label for="<?= Moncine\View::escape($gotoPageId) ?>" class="list-pager__goto-label">Page</label>
        <input type="number" name="page" id="<?= Moncine\View::escape($gotoPageId) ?>"
               class="list-pager__goto-input"
               min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required
               aria-label="Numéro de page">
        <button type="submit" class="list-pager__goto-btn">OK</button>
    </form>
</nav>
