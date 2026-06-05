<?php
/**
 * Pagination des numéros sur le profil public magazine.
 *
 * @var int $targetUserId
 * @var int $seriesId
 * @var int $page
 * @var int $totalPages
 * @var int $listTotal
 * @var string $statut
 * @var string $listMode
 * @var string $sortBy
 * @var string $sortDir
 * @var string $searchQuery
 * @var string $possessionFilter
 * @var string $paginationIdSuffix
 */
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$listTotal = (int) ($listTotal ?? 0);
$seriesId = (int) ($seriesId ?? 0);
$targetUserId = (int) ($targetUserId ?? 0);
$listMode = (string) ($listMode ?? 'collection');
$paginationIdSuffix = (string) ($paginationIdSuffix ?? '');

if ($totalPages <= 1 || $seriesId <= 0 || $targetUserId <= 0) {
    return;
}

$pageLink = static function (int $targetPage) use (
    $targetUserId,
    $seriesId,
    $listMode,
    $statut,
    $sortBy,
    $sortDir,
    $searchQuery,
    $possessionFilter,
    $totalPages
): string {
    $targetPage = max(1, min($targetPage, $totalPages));
    $params = ['statut' => $statut];
    if (trim($searchQuery) !== '') {
        $params['q'] = $searchQuery;
    }
    if ($possessionFilter !== Moncine\MagazineRepository::POSSESSION_ALL) {
        $params['possession'] = $possessionFilter;
    }
    if ($targetPage > 1) {
        $params['page'] = (string) $targetPage;
    }

    return Moncine\View::userProfileMagazineSeriesUrl(
        $targetUserId,
        $seriesId,
        $listMode,
        $sortBy,
        $sortDir,
        $params
    ) . '#magazine-issues-grid';
};

$gotoPageId = 'magazine_profile_goto_page' . $paginationIdSuffix;
?>
<nav class="list-pager magazine-issues-pager" aria-label="Pagination des numéros">
    <span class="list-pager__status">
        Page <?= $page ?> / <?= $totalPages ?>
        <?php if ($listTotal > 0): ?>
            <span class="list-pager__status-meta">(<?= $listTotal ?> numéro<?= $listTotal > 1 ? 's' : '' ?>)</span>
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

    <form method="get" action="/utilisateur-serie-magazine.php#magazine-issues-grid" class="list-pager__goto magazine-issues-pager__goto">
        <input type="hidden" name="id" value="<?= $targetUserId ?>">
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">
        <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy ?? 'numero_ordre') ?>">
        <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir ?? 'desc') ?>">
        <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut ?? Moncine\LibraryStatut::COLLECTION) ?>">
        <?php if (trim((string) ($searchQuery ?? '')) !== ''): ?>
            <input type="hidden" name="q" value="<?= Moncine\View::escape((string) $searchQuery) ?>">
        <?php endif; ?>
        <?php if (($possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL) !== Moncine\MagazineRepository::POSSESSION_ALL): ?>
            <input type="hidden" name="possession" value="<?= Moncine\View::escape((string) $possessionFilter) ?>">
        <?php endif; ?>
        <label for="<?= Moncine\View::escape($gotoPageId) ?>" class="list-pager__goto-label">Page</label>
        <input type="number" name="page" id="<?= Moncine\View::escape($gotoPageId) ?>"
               class="list-pager__goto-input"
               min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required
               aria-label="Numéro de page">
        <button type="submit" class="list-pager__goto-btn">OK</button>
    </form>
</nav>
