<?php
/**
 * Pagination du catalogue admin : précédent / suivant, page N, dernière page.
 *
 * @var string $search
 * @var string $sortBy
 * @var string $sortDir
 * @var int $page
 * @var int $totalPages
 * @var int $totalCount
 * @var string $paginationIdSuffix suffixe pour les id de formulaire (ex. -top)
 * @var string $mediaDomain
 */
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$search = $search ?? '';
$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';
$mediaDomain = $mediaDomain ?? '';
$totalCount = (int) ($totalCount ?? 0);
$paginationIdSuffix = (string) ($paginationIdSuffix ?? '');

if ($totalPages <= 1) {
    return;
}

$pageLink = static function (int $targetPage) use ($search, $sortBy, $sortDir, $totalPages, $mediaDomain): string {
    $targetPage = max(1, min($targetPage, $totalPages));

    return Moncine\View::catalogueUrl($search, $sortBy, $sortDir, $targetPage, $mediaDomain) . '#catalog-list-nav';
};

$gotoPageId = 'catalog_goto_page' . $paginationIdSuffix;
?>
<nav class="list-pager catalog-list-pager" aria-label="Pagination du catalogue">
    <span class="list-pager__status">
        Page <?= $page ?> / <?= $totalPages ?>
        <?php if ($totalCount > 0): ?>
            <span class="list-pager__status-meta">(<?= $totalCount ?> œuvre<?= $totalCount > 1 ? 's' : '' ?>)</span>
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

    <form method="get" action="/catalogue.php#catalog-list-nav" class="list-pager__goto catalog-list-pager__goto">
        <?php if (trim($search) !== ''): ?>
            <input type="hidden" name="q" value="<?= Moncine\View::escape($search) ?>">
        <?php endif; ?>
        <?php if ($sortBy !== '' && $sortBy !== 'titre'): ?>
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
        <?php endif; ?>
        <?php if (strtolower($sortDir) === 'desc'): ?>
            <input type="hidden" name="dir" value="desc">
        <?php endif; ?>
        <?php if ($mediaDomain !== ''): ?>
            <input type="hidden" name="media" value="<?= Moncine\View::escape($mediaDomain) ?>">
        <?php endif; ?>
        <label for="<?= Moncine\View::escape($gotoPageId) ?>" class="list-pager__goto-label">Page</label>
        <input type="number" name="page" id="<?= Moncine\View::escape($gotoPageId) ?>"
               class="list-pager__goto-input"
               min="1" max="<?= $totalPages ?>" value="<?= $page ?>" required
               aria-label="Numéro de page">
        <button type="submit" class="list-pager__goto-btn">OK</button>
    </form>
</nav>
