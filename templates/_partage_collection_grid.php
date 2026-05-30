<?php
/**
 * Liste partagée visiteur — vue vignettes (affiches).
 *
 * @var list<array<string, mixed>> $films
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
$gridSortLink = static function (string $label, string $column) use (
    $rawToken,
    $sortBy,
    $sortDir,
    $query,
    $kindFilter,
    $viewMode
): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\ShareLinkService::sortUrl($rawToken, $column, $sortBy, $sortDir, $query, $kindFilter, $viewMode)
    ) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<div class="collection-grid-bar">
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $gridSortLink('Titre', 'titre'); ?>
        <?php $gridSortLink('Année', 'annee'); ?>
        <?php $gridSortLink('Réalisateur', 'realisateur'); ?>
    </nav>
</div>

<ul class="collection-grid" role="list">
    <?php foreach ($films as $film):
        $filmId = (int) ($film['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
        $filmUrl = Moncine\ShareLinkService::filmUrl(
            $rawToken,
            $filmId,
            Moncine\ShareLinkService::collectionQueryParams($query, $sortBy, $sortDir, $kindFilter, $viewMode)
        );
        $annee = (int) ($film['annee'] ?? 0);
        $kindKey = Moncine\ContentKindFilter::categoryKey($film);
        $targets = $wishlistTargetsByFilmId[$filmId] ?? [];
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($filmUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Affiche de <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="collection-grid__title"><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></h3>
                    <p class="collection-grid__meta">
                        <span class="tag tag--kind tag--kind-<?= Moncine\View::escape($kindKey) ?>">
                            <?= Moncine\View::escape(Moncine\ContentKindFilter::listLabel($film)) ?>
                        </span>
                        <?php if ($annee > 0): ?>
                            <span class="collection-grid__year"><?= $annee ?></span>
                        <?php endif; ?>
                        <?php if ($showWishlistTargets): ?>
                            <?php if ($targets !== []): ?>
                                <span class="collection-grid__targets hint">
                                    <?= Moncine\View::escape(Moncine\View::formatWishlistTargetsSummary($targets)) ?>
                                </span>
                            <?php else: ?>
                                <span class="collection-grid__targets hint">—</span>
                            <?php endif; ?>
                        <?php else:
                            $supportLabel = Moncine\SupportPhysique::label((string) ($film['support_physique'] ?? ''));
                            if ($supportLabel !== ''):
                                ?>
                                <span class="collection-grid__support"><?= Moncine\View::escape($supportLabel) ?></span>
                            <?php endif;
                        endif; ?>
                    </p>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
