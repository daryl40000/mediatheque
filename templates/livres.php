<?php
/**
 * Mes livres — collection.
 *
 * @var list<array<string, mixed>> $books
 * @var string $query
 * @var string $sortBy
 * @var string $sortDir
 * @var string $viewMode
 * @var int $totalCount
 * @var string $moduleError
 */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';
$query = $query ?? '';
$viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
$isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
$isShelfView = Moncine\CollectionViewMode::isShelf($viewMode);
$viewQueryValue = Moncine\CollectionViewMode::queryValue($viewMode);
$totalCount = (int) ($totalCount ?? count($books ?? []));
$moduleError = $moduleError ?? '';
$categoryFilterChoices = Moncine\LivreCategory::filterChoicesForBookList($books ?? []);

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::livresSortUrl($column, $sortBy, $sortDir, $query, false, $viewMode)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page">
    <div class="collection-page__head">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <div class="collection-page__head-actions">
            <a href="<?= Moncine\View::escape(Moncine\View::addLivreUrl(Moncine\LibraryStatut::COLLECTION)) ?>"
               class="btn btn-primary">Ajouter un livre</a>
        </div>
    </div>

    <p class="lead">
        Vos livres papier ou numériques. Les livres de catégorie <strong>Jeux vidéo</strong>
        peuvent être reliés aux jeux de votre catalogue.
    </p>

    <nav class="ui-pill-nav" aria-label="Accès rapides livres">
        <a href="/livres-envies.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></a>
        <a href="/sagas-livres.php" class="ui-pill">Sagas</a>
        <a href="/statistiques.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['stats']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Livre retiré<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
        <div class="alert alert-success">Livre ajouté à votre collection.</div>
    <?php endif; ?>

    <form method="get" action="/livres.php" class="collection-search import-form">
        <label for="livres_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="livres_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, auteur, éditeur, ISBN…"
                   autocomplete="off">
            <?php if ($sortBy !== 'titre'): ?>
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <?php endif; ?>
            <?php if (strtolower($sortDir) === 'desc'): ?>
                <input type="hidden" name="dir" value="desc">
            <?php endif; ?>
            <?php if ($viewQueryValue !== null): ?>
                <input type="hidden" name="view" value="<?= Moncine\View::escape($viewQueryValue) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Rechercher</button>
            <?php if ($query !== ''): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::livresCollectionUrl('', $sortBy, $sortDir, $viewMode)) ?>"
                   class="btn btn-secondary">Effacer</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="ui-pill-bar" aria-label="Mode d’affichage" data-collection-view-memory>
        <?php foreach (Moncine\CollectionViewMode::choices() as $modeKey => $modeLabel): ?>
            <?php
            $modeActive = $viewMode === $modeKey;
            $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
            ?>
            <a href="<?= Moncine\View::escape(Moncine\View::livresCollectionUrl($query, $sortBy, $sortDir, $modeKey)) ?>"
               class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                <?= Moncine\View::escape($modeLabel) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($totalCount === 0): ?>
        <p class="hint">
            Aucun livre en collection.
            <a href="<?= Moncine\View::escape(Moncine\View::addLivreUrl()) ?>">Ajoutez votre premier livre</a>.
        </p>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_livre_category_filter.php'; ?>

        <p class="stats" data-magazine-series-stats
           data-stats-label=" en collection."
           data-stats-label-single=" en collection."
           data-stats-visible=" livre(s) affiché(s)."
           data-stats-visible-single=" livre affiché.">
            <?= $totalCount ?> livre<?= $totalCount > 1 ? 's' : '' ?> en collection.
            <?php if ($query !== ''): ?>
                pour « <?= Moncine\View::escape($query) ?> »
            <?php endif; ?>
        </p>
        <p class="hint">
            <?php if ($isGridView): ?>
                Cliquez sur une vignette pour ouvrir la fiche.
            <?php elseif ($isShelfView): ?>
                Survolez une tranche pour la vignette ; cliquez pour ouvrir la fiche.
            <?php else: ?>
                Cliquez sur un en-tête pour trier.
            <?php endif; ?>
        </p>
        <p class="hint magazine-category-rail__empty" data-magazine-category-empty hidden>
            Aucun livre ne correspond aux catégories sélectionnées.
        </p>

        <?php if ($isGridView): ?>
            <?php require MONCINE_ROOT . '/templates/_livres_collection_grid.php'; ?>
        <?php elseif ($isShelfView): ?>
            <?php require MONCINE_ROOT . '/templates/_livres_collection_shelf.php'; ?>
        <?php else: ?>
            <?php require MONCINE_ROOT . '/templates/_livres_collection_list.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
