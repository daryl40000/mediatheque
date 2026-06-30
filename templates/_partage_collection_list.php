<?php
/**
 * Liste partagée visiteur — vue tableau avec affiches.
 *
 * @var list<array<string, mixed>> $films
 * @var callable $shareSortHeader
 * @var string $rawToken
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $kindFilter
 * @var string $viewMode
 * @var bool $showWishlistTargets
 * @var array<int, list<array<string, mixed>>> $wishlistTargetsByFilmId
 */
$showWishlistTargets = !empty($showWishlistTargets);
$wishlistTargetsByFilmId = $wishlistTargetsByFilmId ?? [];
$listContext = Moncine\ShareLinkService::collectionQueryParams(
    $query ?? '',
    $sortBy ?? 'titre',
    $sortDir ?? 'asc',
    $kindFilter ?? '',
    $viewMode ?? ''
);
?>
<p class="table-scroll-hint show-mobile-only">Faites glisser le tableau horizontalement pour voir toutes les colonnes.</p>
<div class="table-scroll">
<table class="films-table films-table--sortable films-table--wide">
    <thead>
        <tr>
            <th class="col-poster" scope="col">Affiche</th>
            <?php $shareSortHeader('Titre', 'titre'); ?>
            <th scope="col">Type</th>
            <?php $shareSortHeader('Année', 'annee'); ?>
            <?php $shareSortHeader('Réalisateur', 'realisateur'); ?>
            <th scope="col">Style</th>
            <th scope="col">Saga</th>
            <?php if ($showWishlistTargets): ?>
                <th scope="col">Versions recherchées</th>
            <?php else: ?>
                <?php $shareSortHeader('Support', 'support_physique'); ?>
                <?php $shareSortHeader('Note', 'note'); ?>
                <?php $shareSortHeader('Dernière vue', 'derniere_vue'); ?>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($films as $film):
            $filmId = (int) ($film['id'] ?? 0);
            $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
            $filmUrl = Moncine\ShareLinkService::filmUrl($rawToken, $filmId, $listContext);
            $sagaLabel = trim((string) ($film['saga'] ?? ''));
            $sagaOrdre = (int) ($film['saga_ordre'] ?? 0);
            $targets = $wishlistTargetsByFilmId[$filmId] ?? [];
            ?>
            <tr>
                <td class="col-poster">
                    <a href="<?= Moncine\View::escape($filmUrl) ?>" class="films-table__poster-link"
                       title="Voir la fiche : <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="films-table__poster" src="<?= $posterSrc ?>"
                                 alt="Affiche de <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>"
                                 width="44" height="66" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="films-table__poster films-table__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                </td>
                <td>
                    <a href="<?= Moncine\View::escape($filmUrl) ?>" class="film-link">
                        <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>
                    </a>
                </td>
                <td>
                    <span class="tag tag--kind tag--kind-<?= Moncine\View::escape(
                        Moncine\ContentKindFilter::categoryKey($film)
                    ) ?>">
                        <?= Moncine\View::escape(Moncine\ContentKindFilter::listLabel($film)) ?>
                    </span>
                </td>
                <td><?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '—' ?></td>
                <td><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></td>
                <td><?= Moncine\View::escape((string) ($film['styles'] ?? '')) ?></td>
                <td>
                    <?php if ($sagaLabel !== ''): ?>
                        <?= Moncine\View::escape($sagaLabel) ?>
                        <?php if ($sagaOrdre > 0): ?>
                            <span class="hint">(<?= $sagaOrdre ?>)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <?php if ($showWishlistTargets): ?>
                    <td class="wishlist-targets-summary">
                        <?php if ($targets !== []): ?>
                            <?= Moncine\View::escape(Moncine\View::formatWishlistTargetsSummary($targets)) ?>
                        <?php else: ?>
                            <span class="hint">—</span>
                        <?php endif; ?>
                    </td>
                <?php else: ?>
                    <td><?= Moncine\View::escape(
                        Moncine\SupportPhysique::label((string) ($film['support_physique'] ?? '')) ?: '—'
                    ) ?></td>
                    <td><?php $showFoyerAverage = true; $layout = 'stacked'; require MONCINE_ROOT . '/templates/_film_ratings.php'; ?></td>
                    <td><?= !empty($film['derniere_vue'])
                        ? Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $film['derniere_vue']))
                        : '—' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
