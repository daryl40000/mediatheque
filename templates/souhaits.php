<?php
/** @var list<array<string, mixed>> $films */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var string $query */
/** @var bool $searched */
/** @var int $totalCount */
/** @var string $scope */
/** @var bool $canShowGroup */
/** @var string $groupName */
/** @var bool $isGroupScope */
/** @var array<int, list<array<string, mixed>>> $wishlistTargetsByFilmId */

$query = $query ?? '';
$wishlistTargetsByFilmId = $wishlistTargetsByFilmId ?? [];
$scope = $scope ?? Moncine\WishlistScope::MINE;
$isGroupScope = $isGroupScope ?? false;
$canShowGroup = $canShowGroup ?? false;
$groupName = $groupName ?? '';
$filmListContext = Moncine\FilmListContext::forWishlist($sortBy, $sortDir, $query);
$searched = $searched ?? false;
$totalCount = (int) ($totalCount ?? count($films));
$resultCount = count($films);

$sortHeader = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $scope): void {
    $active = $sortBy === $column;
    $aria = $active
        ? (strtolower($sortDir) === 'desc' ? 'descending' : 'ascending')
        : 'none';
    ?>
    <th class="<?= $active ? 'sorted' : '' ?>" aria-sort="<?= $aria ?>">
        <a href="<?= Moncine\View::escape(Moncine\View::wishlistSortUrl($column, $sortBy, $sortDir, $query, $scope)) ?>">
            <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
        </a>
    </th>
    <?php
};
?>
<section class="collection-page wishlist-page">
    <div class="collection-page__head">
        <h1><?= Moncine\View::escape(Moncine\LibraryStatut::label(Moncine\LibraryStatut::WISHLIST)) ?></h1>
        <div class="collection-page__head-actions">
            <?php
            $printUrl = Moncine\View::wishlistPrintUrl($query, $sortBy, $sortDir, $scope);
            require MONCINE_ROOT . '/templates/_print_button.php';
            ?>
            <?php if (!$isGroupScope): ?>
                <a class="btn btn-secondary" href="/gerer-partages.php?scope=<?= Moncine\ShareLinkScope::WISHLIST ?>">
                    Partager mes envies
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canShowGroup): ?>
        <nav class="ui-pill-nav" aria-label="Portée des envies">
            <a href="<?= Moncine\View::escape(Moncine\View::wishlistUrl($query, $sortBy, $sortDir, Moncine\WishlistScope::MINE)) ?>"
               class="ui-pill<?= !$isGroupScope ? ' ui-pill--active' : '' ?>">
                Mes envies
            </a>
            <a href="<?= Moncine\View::escape(Moncine\View::wishlistUrl($query, 'votes', 'desc', Moncine\WishlistScope::GROUP)) ?>"
               class="ui-pill<?= $isGroupScope ? ' ui-pill--active' : '' ?>">
                Envies du groupe<?= $groupName !== '' ? ' — ' . Moncine\View::escape($groupName) : '' ?>
            </a>
        </nav>
    <?php endif; ?>

    <?php if ($isGroupScope): ?>
        <p class="lead">
            Tous les films que les membres du groupe aimeraient voir ou posséder.
            Le classement par <strong>demandes</strong> montre les titres les plus plébiscités.
        </p>
    <?php else: ?>
        <p class="lead">
            Films que vous aimeriez voir ou posséder. Quand vous les avez, ajoutez-les à vos films.
        </p>
    <?php endif; ?>

    <form method="get" action="/souhaits.php" class="collection-search import-form">
        <?php if ($isGroupScope): ?>
            <input type="hidden" name="scope" value="<?= Moncine\View::escape(Moncine\WishlistScope::GROUP) ?>">
        <?php endif; ?>
        <label for="wishlist_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="wishlist_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, réalisateur, acteur, style…"
                   autocomplete="off">
            <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
            <button type="submit" class="btn btn-primary">Rechercher</button>
            <?php if ($searched): ?>
                <a href="<?= Moncine\View::escape(Moncine\View::wishlistUrl('', $sortBy, $sortDir, $scope)) ?>"
                   class="btn btn-secondary">Effacer</a>
            <?php endif; ?>
        </div>
        <div class="collection-search__add">
            <a class="btn btn-primary" href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::WISHLIST)) ?>">
                Ajouter un film
            </a>
        </div>
    </form>

    <?php if (!empty($_GET['promote_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['vote_error'])): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vote_error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['vote_ok'])): ?>
        <p class="alert alert-success">Film ajouté à vos envies.</p>
    <?php endif; ?>

    <?php if ($searched): ?>
        <p class="stats">
            <?= $resultCount ?> résultat<?= $resultCount > 1 ? 's' : '' ?>
            pour « <?= Moncine\View::escape($query) ?> »
        </p>
    <?php elseif ($isGroupScope): ?>
        <p class="stats">
            <?= $totalCount ?> titre<?= $totalCount > 1 ? 's' : '' ?> demandé<?= $totalCount > 1 ? 's' : '' ?> dans le groupe
        </p>
    <?php else: ?>
        <p class="stats"><?= $totalCount ?> film<?= $totalCount > 1 ? 's' : '' ?> dans vos envies</p>
    <?php endif; ?>

    <?php if (!$isGroupScope): ?>
        <p class="hint collection-page__hint">
            Importez un CSV avec la colonne <strong>Statut</strong> = « wishlist », « mes envies » ou « à acheter » pour pré-remplir cette liste.
            <a href="/import.php">Page Importer</a>
        </p>
    <?php endif; ?>

    <?php if ($totalCount === 0 && !$searched && !$isGroupScope): ?>
        <p>Aucun film dans vos envies. <a href="/import.php">Importer une liste</a> ou ajoutez des titres.</p>
    <?php elseif ($totalCount === 0 && !$searched && $isGroupScope): ?>
        <p class="hint">Aucune envie enregistrée dans le groupe pour le moment.</p>
    <?php elseif ($films === []): ?>
        <p class="alert alert-warning">Aucun résultat pour cette recherche.</p>
    <?php elseif ($isGroupScope): ?>
        <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
        <div class="table-scroll">
        <table class="films-table films-table--sortable wishlist-group-table">
            <thead>
                <tr>
                    <?php $sortHeader('Demandes', 'votes'); ?>
                    <?php $sortHeader('Titre', 'titre'); ?>
                    <?php $sortHeader('Année', 'annee'); ?>
                    <th>Personnes</th>
                    <?php $sortHeader('Réalisateur', 'realisateur'); ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($films as $film):
                    $oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
                    $voteCount = (int) ($film['vote_count'] ?? 0);
                    $inMine = !empty($film['in_my_wishlist']);
                    $myFilmId = (int) ($film['id'] ?? 0);
                    $voters = is_array($film['voters'] ?? null) ? $film['voters'] : [];
                    ?>
                    <tr>
                        <td>
                            <span class="wishlist-vote-badge" title="Nombre de membres qui le demandent">
                                <?= $voteCount ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($myFilmId > 0): ?>
                                <a href="<?= Moncine\View::escape($filmListContext->filmUrl($myFilmId)) ?>" class="film-link">
                                    <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>
                                </a>
                            <?php else: ?>
                                <strong><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                        <td class="wishlist-voters">
                            <?= Moncine\View::escape(Moncine\View::formatVoterNames($voters)) ?>
                        </td>
                        <td><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></td>
                        <td class="wishlist-actions">
                            <?php if ($inMine): ?>
                                <span class="hint">Déjà dans vos envies</span>
                            <?php elseif ($oeuvreId > 0): ?>
                                <form method="post" action="/souhaits.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="action" value="vote">
                                    <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">
                                    <input type="hidden" name="scope" value="<?= Moncine\View::escape(Moncine\WishlistScope::GROUP) ?>">
                                    <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
                                    <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
                                    <input type="hidden" name="q" value="<?= Moncine\View::escape($query) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Moi aussi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
        <div class="table-scroll">
        <table class="films-table films-table--sortable">
            <thead>
                <tr>
                    <?php $sortHeader('Titre', 'titre'); ?>
                    <?php $sortHeader('Année', 'annee'); ?>
                    <th>Nationalité</th>
                    <?php $sortHeader('Réalisateur', 'realisateur'); ?>
                    <?php $sortHeader('Style', 'styles'); ?>
                    <th>Versions recherchées</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($films as $film):
                    $filmId = (int) $film['id'];
                    $targets = $wishlistTargetsByFilmId[$filmId] ?? [];
                    ?>
                    <tr>
                        <td>
                            <a href="<?= Moncine\View::escape($filmListContext->filmUrl($filmId)) ?>" class="film-link">
                                <?= Moncine\View::escape($film['titre']) ?>
                            </a>
                        </td>
                        <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                        <td><?= Moncine\View::escape(
                            Moncine\FilmRepository::formatNationalite((string) ($film['nationalite'] ?? ''))
                        ) ?></td>
                        <td><?= Moncine\View::escape($film['realisateur']) ?></td>
                        <td><?= Moncine\View::escape($film['styles']) ?></td>
                        <td class="wishlist-targets-summary">
                            <?php if ($targets !== []): ?>
                                <?= Moncine\View::escape(Moncine\View::formatWishlistTargetsSummary($targets)) ?>
                            <?php else: ?>
                                <span class="hint">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="wishlist-actions">
                            <?php
                            $formAction = '/souhaits.php';
                            $wishlistTargets = $targets;
                            $includeListContext = false;
                            $formClass = 'wishlist-promote-form import-form';
                            $compactPromoteForm = true;
                            $formId = '';
                            $extraHiddenFields = [
                                ['action', 'promote'],
                                ['sort', $sortBy],
                                ['dir', $sortDir],
                                ['q', $query],
                            ];
                            require MONCINE_ROOT . '/templates/_film_promote_wishlist_form.php';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/films.php">← Mes films</a>
    </p>
</section>
