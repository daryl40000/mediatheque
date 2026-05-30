<?php
/** @var array<string, mixed>|null $link */
/** @var list<array<string, mixed>> $films */
?>
<section class="collection-page share-visitor-page">
    <?php if ($link === null): ?>
        <h1>Lien invalide ou expiré</h1>
        <p class="hint">Ce lien de partage n’existe pas, a expiré ou a été révoqué.</p>
    <?php else:
        $sortBy = $sortBy ?? 'titre';
        $sortDir = $sortDir ?? 'asc';
        $query = $query ?? '';
        $kindFilter = Moncine\ContentKindFilter::normalize($kindFilter ?? '');
        $viewMode = Moncine\CollectionViewMode::normalize($viewMode ?? '');
        $isGridView = Moncine\CollectionViewMode::isGrid($viewMode);
        $rawToken = (string) ($rawToken ?? '');
        $resultCount = count($films);
        $totalCount = (int) ($totalCount ?? $resultCount);
        $searched = $searched ?? false;

        $shareCollectionUrl = static function (
            string $q = '',
            ?string $sort = null,
            ?string $dir = null,
            ?string $kind = null,
            ?string $view = null
        ) use ($rawToken, $sortBy, $sortDir, $query, $kindFilter, $viewMode): string {
            return Moncine\ShareLinkService::collectionUrl(
                $rawToken,
                Moncine\ShareLinkService::collectionQueryParams(
                    $q ?? $query,
                    $sort ?? $sortBy,
                    $dir ?? $sortDir,
                    $kind ?? $kindFilter,
                    $view ?? $viewMode
                )
            );
        };

        $shareSortHeader = static function (string $label, string $column) use (
            $rawToken,
            $sortBy,
            $sortDir,
            $query,
            $kindFilter,
            $viewMode
        ): void {
            $active = $sortBy === $column;
            $aria = $active
                ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
                : 'none';
            ?>
            <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>" scope="col">
                <a href="<?= Moncine\View::escape(
                    Moncine\ShareLinkService::sortUrl($rawToken, $column, $sortBy, $sortDir, $query, $kindFilter, $viewMode)
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

        <form method="get" action="/partage.php" class="collection-search import-form">
            <input type="hidden" name="t" value="<?= Moncine\View::escape($rawToken) ?>">
            <label for="share_q">Rechercher</label>
            <div class="collection-search__row">
                <input type="search" name="q" id="share_q"
                       value="<?= Moncine\View::escape($query) ?>"
                       placeholder="Titre, réalisateur, acteur, style, saga…"
                       autocomplete="off">
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
                <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
                <?php if ($kindFilter !== Moncine\ContentKindFilter::ALL): ?>
                    <input type="hidden" name="kind" value="<?= Moncine\View::escape($kindFilter) ?>">
                <?php endif; ?>
                <?php if ($isGridView): ?>
                    <input type="hidden" name="view" value="grid">
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">Rechercher</button>
                <?php if ($searched): ?>
                    <a href="<?= Moncine\View::escape($shareCollectionUrl('', $sortBy, $sortDir, $kindFilter, $viewMode)) ?>"
                       class="btn btn-secondary">Effacer la recherche</a>
                <?php endif; ?>
            </div>
        </form>

        <nav class="ui-pill-nav" aria-label="Filtrer par type">
            <?php foreach (Moncine\ContentKindFilter::choices() as $kindKey => $kindChoiceLabel): ?>
                <?php
                $kindActive = $kindFilter === $kindKey;
                $kindClass = 'ui-pill' . ($kindActive ? ' ui-pill--active' : '');
                ?>
                <a href="<?= Moncine\View::escape($shareCollectionUrl($query, $sortBy, $sortDir, $kindKey, $viewMode)) ?>"
                   class="<?= $kindClass ?>"<?= $kindActive ? ' aria-current="page"' : '' ?>>
                    <?= Moncine\View::escape($kindChoiceLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <nav class="ui-pill-bar" aria-label="Mode d’affichage">
            <?php foreach (Moncine\CollectionViewMode::choices() as $modeKey => $modeLabel): ?>
                <?php
                $modeActive = $viewMode === $modeKey;
                $modeClass = 'ui-pill-bar__item' . ($modeActive ? ' ui-pill--active' : '');
                ?>
                <a href="<?= Moncine\View::escape($shareCollectionUrl($query, $sortBy, $sortDir, $kindFilter, $modeKey)) ?>"
                   class="<?= $modeClass ?>"<?= $modeActive ? ' aria-current="true"' : '' ?>>
                    <?= Moncine\View::escape($modeLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php
        $kindLabel = $kindFilter !== Moncine\ContentKindFilter::ALL
            ? Moncine\ContentKindFilter::label($kindFilter)
            : '';
        ?>
        <?php if ($searched || $kindFilter !== Moncine\ContentKindFilter::ALL): ?>
            <p class="stats">
                <?= $resultCount ?> résultat<?= $resultCount > 1 ? 's' : '' ?>
                <?php if ($kindFilter !== Moncine\ContentKindFilter::ALL): ?>
                    (<?= Moncine\View::escape(mb_strtolower($kindLabel)) ?>)
                <?php endif; ?>
                <?php if ($searched): ?>
                    pour « <?= Moncine\View::escape($query) ?> »
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="stats"><?= $totalCount ?> titre<?= $totalCount > 1 ? 's' : '' ?></p>
        <?php endif; ?>

        <p class="hint collection-page__hint">
            <?php if ($isGridView): ?>
                Cliquez sur une vignette pour ouvrir la fiche du film.
            <?php else: ?>
                Cliquez sur un en-tête pour trier la liste. Cliquez sur une affiche ou un titre pour ouvrir la fiche.
            <?php endif; ?>
        </p>

        <?php if ($totalCount === 0): ?>
            <p class="hint">Aucun titre à afficher pour le moment.</p>
        <?php elseif ($films === []): ?>
            <p class="alert alert-warning">
                <?php if ($searched): ?>
                    Aucun titre ne correspond à « <?= Moncine\View::escape($query) ?> ».
                <?php elseif ($kindFilter !== Moncine\ContentKindFilter::ALL): ?>
                    Aucun <?= Moncine\View::escape(mb_strtolower($kindLabel)) ?> dans cette liste.
                <?php else: ?>
                    Aucun titre ne correspond à votre recherche.
                <?php endif; ?>
                <a href="<?= Moncine\View::escape($shareCollectionUrl($searched ? '' : $query, $sortBy, $sortDir, $kindFilter, $viewMode)) ?>">
                    Voir toute la liste
                </a>.
            </p>
        <?php elseif ($isGridView): ?>
            <?php require MONCINE_ROOT . '/templates/_partage_collection_grid.php'; ?>
        <?php else: ?>
            <?php require MONCINE_ROOT . '/templates/_partage_collection_list.php'; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
