<?php
/** @var list<array<string, mixed>> $films */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $query */
/** @var bool $searched */
/** @var int $totalCount */
/** @var int $listTotal */
/** @var int $page */
/** @var int $totalPages */
/** @var int $perPage */
/** @var list<string> $existingSagas */
/** @var bool $hasTmdbKey */

$query = $query ?? '';
$kindFilter = \Moncine\ContentKindFilter::normalize($kindFilter ?? '');
$viewMode = \Moncine\CollectionViewMode::normalize($viewMode ?? '');
$isGridView = \Moncine\CollectionViewMode::isGrid($viewMode);
$hasTmdbKey = $hasTmdbKey ?? false;
$searched = $searched ?? false;
$totalCount = (int) ($totalCount ?? count($films));
$listTotal = (int) ($listTotal ?? $totalCount);
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$perPage = (int) ($perPage ?? count($films));
$resultCount = count($films);
$existingSagas = $existingSagas ?? [];

$bulkOk = isset($_GET['bulk_ok']) ? (int) $_GET['bulk_ok'] : null;
$bulkMsg = trim((string) ($_GET['bulk_msg'] ?? ''));
$bulkError = trim((string) ($_GET['bulk_error'] ?? ''));
$bulkDetail = trim((string) ($_GET['bulk_detail'] ?? ''));
$sagaNameFlash = trim((string) ($_GET['saga_name'] ?? ''));
$deletedFlash = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$deletedTitle = trim((string) ($_GET['deleted_title'] ?? ''));

$filmListContext = Moncine\FilmListContext::forCollection($sortBy, $sortDir, $query, $kindFilter);

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $kindFilter, $viewMode): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::filmsSortUrl($column, $sortBy, $sortDir, $query, $kindFilter, $viewMode)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page">
    <div class="collection-page__head">
        <h1>Mes films</h1>
        <div class="collection-page__head-actions">
            <?php
            $printUrl = Moncine\View::filmsPrintUrl($query, $sortBy, $sortDir, $kindFilter);
            require MONCINE_ROOT . '/templates/_print_button.php';
            ?>
            <a class="btn btn-secondary" href="/gerer-partages.php?scope=<?= Moncine\ShareLinkScope::COLLECTION ?>">
                Partager
            </a>
            <a class="btn btn-primary" href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::COLLECTION)) ?>">
                Ajouter un film
            </a>
        </div>
    </div>

    <form method="get" action="/films.php" class="collection-search import-form">
        <label for="collection_q">Rechercher un film</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="collection_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, réalisateur, acteur, style, saga…"
                   autocomplete="off">
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <?php if ($kindFilter !== \Moncine\ContentKindFilter::ALL): ?>
                <input type="hidden" name="kind" value="<?= Moncine\View::escape($kindFilter) ?>">
            <?php endif; ?>
            <?php if ($isGridView): ?>
                <input type="hidden" name="view" value="grid">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Rechercher</button>
            <?php if ($searched): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::filmsCollectionUrl('', $sortBy, $sortDir, $kindFilter, $viewMode)) ?>"
                   class="btn btn-secondary">Effacer la recherche</a>
            <?php endif; ?>
        </div>
    </form>

    <nav class="ui-pill-nav" aria-label="Filtrer par type et accéder aux sagas">
        <?php foreach (\Moncine\ContentKindFilter::choices() as $kindKey => $kindChoiceLabel): ?>
            <?php
            $kindActive = $kindFilter === $kindKey;
            $kindClass = 'ui-pill' . ($kindActive ? ' ui-pill--active' : '');
            ?>
            <a href="<?= Moncine\View::escape(Moncine\View::filmsCollectionUrl($query, $sortBy, $sortDir, $kindKey, $viewMode)) ?>"
               class="<?= $kindClass ?>"<?= $kindActive ? ' aria-current="page"' : '' ?>>
                <?= Moncine\View::escape($kindChoiceLabel) ?>
            </a>
        <?php endforeach; ?>
        <a href="/sagas.php" class="ui-pill ui-pill--outline-accent">
            Sagas
        </a>
    </nav>

    <nav class="ui-pill-bar" aria-label="Mode d’affichage">
        <?php foreach (\Moncine\CollectionViewMode::choices() as $modeKey => $modeLabel): ?>
            <?php
            $modeActive = $viewMode === $modeKey;
            $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
            ?>
            <a href="<?= Moncine\View::escape(Moncine\View::filmsCollectionUrl($query, $sortBy, $sortDir, $kindFilter, $modeKey)) ?>"
               class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                <?= Moncine\View::escape($modeLabel) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($deletedFlash): ?>
        <p class="alert alert-success">
            Film supprimé de vos films<?= $deletedTitle !== ''
                ? ' : « ' . Moncine\View::escape($deletedTitle) . ' »'
                : '' ?>.
        </p>
    <?php endif; ?>
    <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
        <p class="alert alert-success">
            Film ajouté à vos films<?= !empty($_GET['promoted_title'])
                ? ' : « ' . Moncine\View::escape((string) $_GET['promoted_title']) . ' »'
                : '' ?>.
        </p>
    <?php endif; ?>
    <?php if ($bulkMsg !== ''): ?>
        <p class="alert <?= ($bulkOk ?? 0) > 0 ? 'alert-success' : 'alert-warning' ?>">
            <?= Moncine\View::escape($bulkMsg) ?>
            <?php if ($sagaNameFlash !== ''): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::sagaUrl($sagaNameFlash)) ?>">Voir la saga</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($bulkError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($bulkError) ?></p>
    <?php endif; ?>
    <?php if ($bulkDetail !== ''): ?>
        <p class="hint"><?= Moncine\View::escape($bulkDetail) ?></p>
    <?php endif; ?>

    <?php
    $kindLabel = $kindFilter !== \Moncine\ContentKindFilter::ALL
        ? \Moncine\ContentKindFilter::label($kindFilter)
        : '';
    ?>
    <?php if ($searched || $kindFilter !== \Moncine\ContentKindFilter::ALL): ?>
        <p class="stats">
            <?= $listTotal ?> résultat<?= $listTotal > 1 ? 's' : '' ?>
            <?php if ($kindFilter !== \Moncine\ContentKindFilter::ALL): ?>
                (<?= Moncine\View::escape(mb_strtolower($kindLabel)) ?>)
            <?php endif; ?>
            <?php if ($searched): ?>
                pour « <?= Moncine\View::escape($query) ?> »
            <?php endif; ?>
            <?php if ($totalCount > $listTotal && ($searched || $kindFilter !== \Moncine\ContentKindFilter::ALL)): ?>
                (sur <?= $totalCount ?> film<?= $totalCount > 1 ? 's' : '' ?> au total)
            <?php endif; ?>
            <?php if ($totalPages > 1): ?>
                — page <?= $page ?> / <?= $totalPages ?>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="stats">
            <?= $totalCount ?> film<?= $totalCount > 1 ? 's' : '' ?>
            <?php if ($totalPages > 1): ?>
                — page <?= $page ?> / <?= $totalPages ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <p class="hint collection-page__hint">
        <?php if ($isGridView): ?>
            Cliquez sur une vignette pour ouvrir la fiche. Cochez les titres pour les actions de masse (saga, support, TMDB…).
        <?php else: ?>
            Cliquez sur un en-tête pour trier. Cochez un ou plusieurs films pour afficher les actions de masse.
        <?php endif; ?>
    </p>

    <?php if ($totalCount === 0): ?>
        <p>Aucun film. <a href="/import.php">Importer un CSV</a>.</p>
    <?php elseif ($listTotal === 0): ?>
        <p class="alert alert-warning">
            <?php if ($searched): ?>
                Aucun film ne correspond à « <?= Moncine\View::escape($query) ?> ».
            <?php elseif ($kindFilter !== \Moncine\ContentKindFilter::ALL): ?>
                Aucun <?= Moncine\View::escape(mb_strtolower($kindLabel)) ?> parmi vos films.
            <?php else: ?>
                Aucun film ne correspond à votre recherche.
            <?php endif; ?>
            <a href="<?= Moncine\View::escape(Moncine\View::filmsCollectionUrl($searched ? '' : $query, $sortBy, $sortDir, $kindFilter, $viewMode)) ?>">Voir tous mes films</a>.
        </p>
    <?php else: ?>
        <div id="films-collection">
            <?php
            $paginationIdSuffix = '-top';
            require MONCINE_ROOT . '/templates/_films_collection_pagination.php';
            ?>
        <form method="post" action="/films.php" class="collection-bulk-form" id="collection-bulk-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <input type="hidden" name="q" value="<?= Moncine\View::escape($query) ?>">
            <?php if ($kindFilter !== \Moncine\ContentKindFilter::ALL): ?>
                <input type="hidden" name="kind" value="<?= Moncine\View::escape($kindFilter) ?>">
            <?php endif; ?>
            <?php if ($isGridView): ?>
                <input type="hidden" name="view" value="grid">
            <?php endif; ?>
            <?php if ($page > 1): ?>
                <input type="hidden" name="page" value="<?= $page ?>">
            <?php endif; ?>

            <div class="collection-toolbar" id="collection-toolbar" hidden>
                <div class="collection-toolbar__head">
                    <p class="collection-toolbar__count">
                        <span id="collection-selected-count">0</span>
                        film<span class="collection-toolbar__count-plural">s</span> sélectionné<span class="collection-toolbar__count-plural">s</span>
                    </p>
                    <button type="button" class="btn btn-ghost btn-sm" id="collection-deselect-all">
                        Tout décocher
                    </button>
                </div>

                <div class="collection-toolbar__tabs" role="tablist" aria-label="Actions sur la sélection">
                    <button type="button" class="collection-toolbar__tab is-active"
                            role="tab" aria-selected="true" aria-controls="collection-panel-saga"
                            data-bulk-tab="saga" id="collection-tab-saga">Saga</button>
                    <button type="button" class="collection-toolbar__tab"
                            role="tab" aria-selected="false" aria-controls="collection-panel-support"
                            data-bulk-tab="support" id="collection-tab-support">Support</button>
                    <?php if (!empty($canManageCatalog)): ?>
                    <button type="button" class="collection-toolbar__tab"
                            role="tab" aria-selected="false" aria-controls="collection-panel-tmdb"
                            data-bulk-tab="tmdb" id="collection-tab-tmdb">TMDB</button>
                    <?php endif; ?>
                    <button type="button" class="collection-toolbar__tab collection-toolbar__tab--danger"
                            role="tab" aria-selected="false" aria-controls="collection-panel-delete"
                            data-bulk-tab="delete" id="collection-tab-delete">Supprimer</button>
                </div>

                <div class="collection-toolbar__panels">
                    <div class="collection-toolbar__panel is-active import-form"
                         id="collection-panel-saga" role="tabpanel" aria-labelledby="collection-tab-saga">
                        <p class="collection-toolbar__panel-intro">
                            Numéros 1, 2, 3… dans l’ordre du tableau (triez la liste si besoin).
                        </p>
                        <div class="collection-toolbar__fields">
                            <div>
                                <label for="saga_existing">Saga existante</label>
                                <select name="saga_existing" id="saga_existing">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($existingSagas as $sagaName): ?>
                                        <option value="<?= Moncine\View::escape($sagaName) ?>">
                                            <?= Moncine\View::escape($sagaName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="saga_new">Ou nouvelle saga</label>
                                <input type="text" name="saga_new" id="saga_new"
                                       placeholder="ex. Jason Bourne"
                                       autocomplete="off">
                            </div>
                            <div class="collection-toolbar__field-narrow">
                                <label for="saga_ordre_start">N° de départ</label>
                                <input type="number" name="saga_ordre_start" id="saga_ordre_start"
                                       value="1" min="1" max="999" step="1">
                            </div>
                        </div>
                        <button type="submit" name="action" value="assign_saga" class="btn btn-primary btn-sm">
                            Ajouter à la saga
                        </button>
                    </div>

                    <div class="collection-toolbar__panel import-form"
                         id="collection-panel-support" role="tabpanel" aria-labelledby="collection-tab-support" hidden>
                        <p class="collection-toolbar__panel-intro">
                            Même support physique pour tous les films cochés.
                        </p>
                        <div class="collection-toolbar__fields collection-toolbar__fields--inline">
                            <div>
                                <label for="bulk_support_physique">Support</label>
                                <select name="bulk_support_physique" id="bulk_support_physique">
                                    <option value="">— Non renseigné —</option>
                                    <?php foreach (Moncine\SupportPhysique::choices() as $key => $label): ?>
                                        <option value="<?= Moncine\View::escape($key) ?>">
                                            <?= Moncine\View::escape($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="action" value="set_support" class="btn btn-primary btn-sm">
                                Appliquer
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($canManageCatalog)): ?>
                    <div class="collection-toolbar__panel import-form"
                         id="collection-panel-tmdb" role="tabpanel" aria-labelledby="collection-tab-tmdb" hidden>
                        <p class="collection-toolbar__panel-intro">
                            Rafraîchit fiche, affiche, acteurs… Seuls les films avec un identifiant TMDB sont traités.
                        </p>
                        <?php if (!$hasTmdbKey): ?>
                            <p class="alert alert-warning">
                                Clé API TMDB manquante.
                                <a href="/import.php">Configurer</a>
                            </p>
                        <?php else: ?>
                            <button type="submit" name="action" value="enrich_tmdb" class="btn btn-primary btn-sm"
                                    id="collection-enrich-tmdb-btn">
                                Mettre à jour via TMDB
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="collection-toolbar__panel collection-toolbar__panel--danger import-form"
                         id="collection-panel-delete" role="tabpanel" aria-labelledby="collection-tab-delete" hidden>
                        <p class="collection-toolbar__panel-intro">
                            Retire les films de la dvdthèque et leur historique de visions. Irréversible.
                        </p>
                        <button type="submit" name="action" value="delete_films" class="btn btn-danger btn-sm"
                                id="collection-delete-films-btn">
                            Supprimer la sélection
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($isGridView): ?>
                <?php require MONCINE_ROOT . '/templates/_films_collection_grid.php'; ?>
            <?php else: ?>
                <?php require MONCINE_ROOT . '/templates/_films_collection_list.php'; ?>
            <?php endif; ?>
        </form>
            <?php
            $paginationIdSuffix = '-bottom';
            require MONCINE_ROOT . '/templates/_films_collection_pagination.php';
            ?>
        </div>
    <?php endif; ?>

</section>
