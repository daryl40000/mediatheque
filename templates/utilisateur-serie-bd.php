<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var int $targetUserId
 * @var string $profileDomain
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>> $tomes
 * @var string $listMode
 * @var string $statut
 * @var string $kindLabel
 * @var string $searchQuery
 * @var bool $hasSearch
 * @var int $totalCount
 * @var string $sortBy
 * @var string $sortDir
 */
?>
<section class="account-page social-profile-page">
    <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>

    <?php if ($accessDenied !== '' || $profileUser === null || $series === null): ?>
        <h1>Série BD</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Série introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>">← Retour au profil</a>
        </p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        $displayName = Moncine\UserProfile::displayName($profileUser);
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>">
                <?= Moncine\View::escape($displayName) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($series['titre'] ?? 'Série')) ?></span>
        </p>

        <header class="magazine-series-header">
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::BD)) ?>"
                   class="btn btn-secondary btn-sm">← Retour au profil</a>
            </p>
            <div class="magazine-series-header__main">
                <?php if ($posterSrc !== ''): ?>
                    <img src="<?= $posterSrc ?>" alt="" class="magazine-cover magazine-cover--header">
                <?php endif; ?>
                <div>
                    <h1><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h1>
                    <p class="lead">
                        Collection de <strong><?= Moncine\View::escape($displayName) ?></strong>
                        · <?= Moncine\View::escape($kindLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="hint">Lecture seule.</p>
                </div>
            </div>
        </header>

        <?php if ($totalCount > 0 || $hasSearch): ?>
            <form method="get" action="/utilisateur-serie-bd.php" class="collection-search import-form">
                <input type="hidden" name="id" value="<?= $targetUserId ?>">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
                <label for="profile_bd_q">Rechercher un tome</label>
                <input type="search" name="q" id="profile_bd_q"
                       value="<?= Moncine\View::escape($searchQuery) ?>"
                       placeholder="Auteur, genre…">
                <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                <?php if ($hasSearch): ?>
                    <a href="<?= Moncine\View::escape(
                        Moncine\View::userProfileBdSeriesUrl($targetUserId, $seriesId, $listMode)
                    ) ?>" class="btn btn-secondary btn-sm">Effacer</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($tomes === []): ?>
            <p class="hint">
                <?php if ($hasSearch): ?>
                    Aucun tome ne correspond à votre recherche.
                <?php elseif ($isWishlist): ?>
                    Aucun tome dans les envies pour cette série.
                <?php else: ?>
                    Aucun tome référencé dans cette série.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="stats">
                <?= (int) $totalCount ?> tome<?= (int) $totalCount > 1 ? 's' : '' ?>
                <?= $isWishlist ? 'en envies' : 'dans la collection' ?>.
            </p>
            <?php
            $tomeUrlForBibId = static fn (int $bibId): string => Moncine\View::userProfileBdAlbumUrl($targetUserId, $bibId);
            require MONCINE_ROOT . '/templates/_user_public_bd_tomes_grid.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
