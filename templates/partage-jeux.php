<?php
/** @var array<string, mixed>|null $link */
/** @var list<array<string, mixed>> $games */
?>
<section class="collection-page share-visitor-page">
    <?php if ($link === null): ?>
        <h1>Lien invalide ou expiré</h1>
        <p class="hint">Ce lien de partage n’existe pas, a expiré ou a été révoqué.</p>
    <?php else:
        $sortBy = $sortBy ?? 'titre';
        $sortDir = $sortDir ?? 'asc';
        $query = $query ?? '';
        $viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
        $isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
        $isShelfView = Moncine\CollectionViewMode::isShelf($viewMode);
        $viewQueryValue = Moncine\CollectionViewMode::queryValue($viewMode);
        $rawToken = (string) ($rawToken ?? '');
        $resultCount = count($games);
        $totalCount = (int) ($totalCount ?? $resultCount);
        $searched = $searched ?? false;
        $listFilter = $listFilter ?? Moncine\GameListFilter::empty();
        $filterActive = $listFilter->isActive();
        $filterLabel = $listFilter->activeLabel();
        $mediaDomain = Moncine\MediaDomain::JEU;

        $shareCollectionUrl = static function (
            string $q = '',
            ?string $sort = null,
            ?string $dir = null,
            ?string $view = null,
            ?Moncine\GameListFilter $filter = null
        ) use ($rawToken, $sortBy, $sortDir, $query, $viewMode, $listFilter, $mediaDomain): string {
            return Moncine\ShareLinkService::collectionUrl(
                $rawToken,
                Moncine\ShareLinkService::collectionQueryParams(
                    $q ?? $query,
                    $sort ?? $sortBy,
                    $dir ?? $sortDir,
                    '',
                    $view ?? $viewMode,
                    $filter ?? $listFilter
                ),
                $mediaDomain
            );
        };

        $shareSortHeader = static function (string $label, string $column) use (
            $rawToken,
            $sortBy,
            $sortDir,
            $query,
            $viewMode,
            $listFilter,
            $mediaDomain
        ): void {
            $active = $sortBy === $column;
            $aria = $active
                ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
                : 'none';
            ?>
            <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>" scope="col">
                <a href="<?= Moncine\View::escape(
                    Moncine\ShareLinkService::sortUrl(
                        $rawToken,
                        $column,
                        $sortBy,
                        $sortDir,
                        $query,
                        '',
                        $viewMode,
                        $mediaDomain,
                        $listFilter
                    )
                ) ?>">
                    <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
                </a>
            </th>
            <?php
        };
        ?>
        <header class="collection-page__head share-visitor-page__head">
            <div>
                <p class="hint">Lecture seule — partagé par <?= Moncine\View::escape($ownerLabel ?? '') ?></p>
                <h1><?= Moncine\View::escape($scopeLabel ?? '') ?></h1>
            </div>
        </header>

        <?php if ($filterActive): ?>
            <div class="alert alert-info collection-filter-banner">
                Filtre actif : <strong><?= Moncine\View::escape($filterLabel) ?></strong>.
                <a href="<?= Moncine\View::escape($shareCollectionUrl('', $sortBy, $sortDir, $viewMode, Moncine\GameListFilter::empty())) ?>">
                    Afficher toute la liste
                </a>
            </div>
        <?php endif; ?>

        <form method="get" action="/partage-jeux.php" class="collection-search collection-search--filters">
            <input type="hidden" name="t" value="<?= Moncine\View::escape($rawToken) ?>">
            <div class="collection-search__toolbar">
                <div class="collection-search__filter collection-search__filter--query">
                    <label for="share_q">Rechercher</label>
                    <input type="search" name="q" id="share_q"
                           value="<?= Moncine\View::escape($query) ?>"
                           placeholder="Titre, studio, genre…"
                           autocomplete="off">
                </div>
                <?php require MONCINE_ROOT . '/templates/_games_collection_search_filters.php'; ?>
                <div class="collection-search__actions">
                    <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
                    <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
                    <?php if ($viewQueryValue !== null): ?>
                        <input type="hidden" name="view" value="<?= Moncine\View::escape($viewQueryValue) ?>">
                    <?php endif; ?>
                    <?php
                    foreach ($listFilter->toQueryParams() as $filterKey => $filterValue):
                        if (in_array($filterKey, ['platform', 'store', 'support'], true)) {
                            continue;
                        }
                        ?>
                        <input type="hidden" name="<?= Moncine\View::escape((string) $filterKey) ?>"
                               value="<?= Moncine\View::escape((string) $filterValue) ?>">
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                    <?php if ($searched): ?>
                        <a href="<?= Moncine\View::escape($shareCollectionUrl('', $sortBy, $sortDir, $viewMode, Moncine\GameListFilter::empty())) ?>"
                           class="btn btn-secondary btn-sm">Tout effacer</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <nav class="ui-pill-bar" aria-label="Mode d’affichage">
            <?php foreach (Moncine\CollectionViewMode::gameChoices() as $modeKey => $modeLabel): ?>
                <?php
                $modeActive = $viewMode === $modeKey;
                $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
                ?>
                <a href="<?= Moncine\View::escape($shareCollectionUrl($query, $sortBy, $sortDir, $modeKey)) ?>"
                   class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                    <?= Moncine\View::escape($modeLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($searched): ?>
            <p class="stats">
                <?= $resultCount ?> résultat<?= $resultCount > 1 ? 's' : '' ?>
                <?php if ($query !== ''): ?>
                    pour « <?= Moncine\View::escape($query) ?> »
                <?php elseif ($filterActive): ?>
                    (filtre : <?= Moncine\View::escape($filterLabel) ?>)
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="stats"><?= $totalCount ?> jeu<?= $totalCount > 1 ? 'x' : '' ?></p>
        <?php endif; ?>

        <p class="hint collection-page__hint">
            <?php if ($isGridView): ?>
                Cliquez sur une vignette pour ouvrir la fiche du jeu.
            <?php elseif ($isShelfView): ?>
                Survolez une tranche pour la vignette ; cliquez pour ouvrir la fiche.
            <?php else: ?>
                Cliquez sur un en-tête pour trier la liste. Cliquez sur une jaquette ou un titre pour ouvrir la fiche.
            <?php endif; ?>
        </p>

        <?php if ($totalCount === 0): ?>
            <p class="hint">Aucun jeu à afficher pour le moment.</p>
        <?php elseif ($games === []): ?>
            <p class="alert alert-warning">
                <?php if ($query !== ''): ?>
                    Aucun jeu ne correspond à « <?= Moncine\View::escape($query) ?> ».
                <?php else: ?>
                    Aucun jeu ne correspond à ce filtre.
                <?php endif; ?>
                <a href="<?= Moncine\View::escape($shareCollectionUrl('', $sortBy, $sortDir, $viewMode, Moncine\GameListFilter::empty())) ?>">
                    Voir toute la liste
                </a>.
            </p>
        <?php elseif ($isGridView): ?>
            <?php require MONCINE_ROOT . '/templates/_partage_games_grid.php'; ?>
        <?php elseif ($isShelfView): ?>
            <?php require MONCINE_ROOT . '/templates/_partage_games_shelf.php'; ?>
        <?php else: ?>
            <?php require MONCINE_ROOT . '/templates/_partage_games_list.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
