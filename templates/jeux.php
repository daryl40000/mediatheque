<?php
/** @var list<array<string, mixed>> $games */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $viewMode */
/** @var int $totalCount */
/** @var string $moduleError */
/** @var Moncine\GameListFilter $listFilter */
/** @var list<string> $existingFranchises */
/** @var list<string> $knownSagas */

$sortBy = $sortBy ?? 'titre';
$sortDir = $sortDir ?? 'asc';
$viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
$listFilter = $listFilter ?? Moncine\GameListFilter::empty();
$existingFranchises = $existingFranchises ?? [];
$knownSagas = $knownSagas ?? [];
$isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
$filterActive = $listFilter->isActive();
$filterLabel = $listFilter->activeLabel();
$bulkMsg = trim((string) ($_GET['bulk_msg'] ?? ''));
$bulkError = trim((string) ($_GET['bulk_error'] ?? ''));
$bulkOk = isset($_GET['bulk_ok']) ? (int) $_GET['bulk_ok'] : 0;
$franchiseNameFlash = trim((string) ($_GET['franchise_name'] ?? ''));
$canAssignFranchise = Moncine\GameFranchiseRepository::isAvailable();

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode, $listFilter): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::gamesSortUrl($column, $sortBy, $sortDir, $query, $viewMode, $listFilter)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos jeux vidéo (physiques ou dématérialisés). Les fiches peuvent être reliées aux
            <strong>tests et previews</strong> de vos magazines (PC Jeux, Joystick…).
        </p>
        <div class="collection-page__actions">
            <a href="/ajouter-jeu.php" class="btn btn-accent">Ajouter un jeu</a>
            <a href="/jeux-envies.php" class="btn btn-secondary">Mes envies jeux</a>
            <a href="/statistiques.php" class="btn btn-secondary">Statistiques jeux</a>
            <?php if ($canAssignFranchise): ?>
                <a href="/sagas-jeux.php" class="btn btn-secondary">Sagas</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success">
            Jeu retiré<?= !empty($_GET['deleted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['deleted_title']) . ' »'
                : '' ?>.
        </div>
    <?php endif; ?>

    <?php if ($filterActive): ?>
        <div class="alert alert-info collection-filter-banner">
            Filtre actif : <strong><?= Moncine\View::escape($filterLabel) ?></strong>.
            <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl($query, $sortBy, $sortDir, $viewMode)) ?>">
                Afficher toute la collection
            </a>
        </div>
    <?php endif; ?>

    <form method="get" action="/jeux.php" class="collection-search">
        <label for="jeux_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="jeux_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, studio, genre…">
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <?php if ($isGridView): ?>
                <input type="hidden" name="view" value="grid">
            <?php endif; ?>
            <?php foreach ($listFilter->toQueryParams() as $filterKey => $filterValue): ?>
                <input type="hidden" name="<?= Moncine\View::escape((string) $filterKey) ?>"
                       value="<?= Moncine\View::escape((string) $filterValue) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
            <?php if ($query !== ''): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl('', $sortBy, $sortDir, $viewMode, $listFilter)) ?>"
                   class="btn btn-secondary btn-sm">Effacer la recherche</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="ui-pill-bar" aria-label="Mode d’affichage">
        <?php foreach (Moncine\CollectionViewMode::choices() as $modeKey => $modeLabel): ?>
            <?php
            $modeActive = $viewMode === $modeKey;
            $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
            ?>
            <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl($query, $sortBy, $sortDir, $modeKey, $listFilter)) ?>"
               class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                <?= Moncine\View::escape($modeLabel) ?>
            </a>
        <?php endforeach; ?>
        <?php if ($canAssignFranchise): ?>
            <a href="/sagas-jeux.php" class="ui-pill ui-pill--outline-accent">Sagas</a>
        <?php endif; ?>
    </nav>

    <?php if ($bulkMsg !== ''): ?>
        <p class="alert <?= $bulkOk > 0 ? 'alert-success' : 'alert-warning' ?>">
            <?= Moncine\View::escape($bulkMsg) ?>
            <?php if ($franchiseNameFlash !== ''): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::gameFranchiseUrl($franchiseNameFlash)) ?>">Voir la saga</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($bulkError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($bulkError) ?></p>
    <?php endif; ?>

    <?php if ($totalCount === 0): ?>
        <p class="hint">
            <?php if ($filterActive || $query !== ''): ?>
                Aucun jeu ne correspond à ce filtre.
                <a href="<?= Moncine\View::escape(Moncine\View::gamesCollectionUrl('', $sortBy, $sortDir, $viewMode)) ?>">
                    Voir toute la collection
                </a>.
            <?php else: ?>
                Aucun jeu dans votre collection pour l’instant.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="hint">
            <?= (int) $totalCount ?> jeu<?= $totalCount > 1 ? 'x' : '' ?> trouvé<?= $totalCount > 1 ? 's' : '' ?>.
            <?php if ($isGridView): ?>
                Cliquez sur une vignette pour ouvrir la fiche.
            <?php else: ?>
                Cliquez sur un en-tête de colonne pour trier.
            <?php endif; ?>
            <?php if ($canAssignFranchise): ?>
                Cochez des titres pour les regrouper dans une saga.
            <?php endif; ?>
        </p>
        <?php if ($canAssignFranchise): ?>
        <form method="post" action="/jeux.php" class="collection-bulk-form" id="collection-bulk-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <input type="hidden" name="q" value="<?= Moncine\View::escape($query) ?>">
            <?php if ($isGridView): ?>
                <input type="hidden" name="view" value="grid">
            <?php endif; ?>
            <?php foreach ($listFilter->toQueryParams() as $filterKey => $filterValue): ?>
                <input type="hidden" name="<?= Moncine\View::escape((string) $filterKey) ?>"
                       value="<?= Moncine\View::escape((string) $filterValue) ?>">
            <?php endforeach; ?>

            <div class="collection-toolbar" id="collection-toolbar" hidden>
                <div class="collection-toolbar__head">
                    <p class="collection-toolbar__count">
                        <span id="collection-selected-count">0</span>
                        jeu<span class="collection-toolbar__count-plural">x</span> sélectionné<span class="collection-toolbar__count-plural">s</span>
                    </p>
                    <button type="button" class="btn btn-ghost btn-sm" id="collection-deselect-all">
                        Tout décocher
                    </button>
                </div>

                <div class="collection-toolbar__panels">
                    <div class="collection-toolbar__panel is-active import-form"
                         id="collection-panel-franchise" role="tabpanel">
                        <p class="collection-toolbar__panel-intro">
                            Regroupez les jeux sélectionnés dans une saga (nom IGDB ou saisie manuelle).
                        </p>
                        <div class="collection-toolbar__fields">
                            <div>
                                <label for="franchise_existing">Saga existante</label>
                                <select name="franchise_existing" id="franchise_existing">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($existingFranchises as $franchiseHint): ?>
                                        <option value="<?= Moncine\View::escape($franchiseHint) ?>">
                                            <?= Moncine\View::escape($franchiseHint) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="franchise_new">Ou nouvelle saga</label>
                                <input type="text" name="franchise_new" id="franchise_new" maxlength="120"
                                       placeholder="ex. The Witcher"
                                       autocomplete="off"
                                       list="game-saga-suggestions">
                            </div>
                        </div>
                        <?php require MONCINE_ROOT . '/templates/_game_saga_datalist.php'; ?>
                        <button type="submit" name="action" value="assign_franchise" class="btn btn-primary btn-sm">
                            Ajouter à une saga
                        </button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
        <?php if ($isGridView): ?>
            <?php require MONCINE_ROOT . '/templates/_games_collection_grid.php'; ?>
        <?php else: ?>
            <?php require MONCINE_ROOT . '/templates/_games_collection_list.php'; ?>
        <?php endif; ?>
        <?php if ($canAssignFranchise): ?>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
