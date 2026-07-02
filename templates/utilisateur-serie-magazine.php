<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var int $targetUserId
 * @var string $profileDomain
 * @var array<string, mixed>|null $series
 * @var list<array<string, mixed>> $issues
 * @var string $listMode
 * @var string $statut
 * @var string $publicationTypeLabel
 * @var string $searchQuery
 * @var bool $hasSearch
 * @var int $totalAllIssues
 * @var int $filteredCount
 * @var string $possessionFilter
 * @var int $totalWithPossessionFilter
 * @var int $page
 * @var int $totalPages
 * @var int $perPage
 * @var int $listTotal
 * @var string $sortBy
 * @var string $sortDir
 */
?>
<section class="account-page social-profile-page">
    <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>

    <?php if ($accessDenied !== '' || $profileUser === null || $series === null): ?>
        <h1>Série magazine</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Série introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>">← Retour au profil</a>
        </p>
    <?php else: ?>
        <?php
        $seriesId = (int) ($series['id'] ?? 0);
        $posterSrc = Moncine\View::seriesPosterSrc($series);
        $isWishlist = $statut === Moncine\LibraryStatut::WISHLIST;
        $displayName = Moncine\UserProfile::displayName($profileUser);
        $possessionFilter = $possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL;
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>">
                <?= Moncine\View::escape($displayName) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($series['titre'] ?? 'Série')) ?></span>
        </p>

        <header class="magazine-series-header">
            <p>
                <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, Moncine\MediaDomain::MAGAZINE)) ?>"
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
                        · <?= Moncine\View::escape($publicationTypeLabel) ?>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            · <?= Moncine\View::escape((string) $series['editeur']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="hint">Lecture seule — les PDF ne sont pas partagés.</p>
                </div>
            </div>
        </header>

        <?php if ($totalAllIssues > 0 || $hasSearch): ?>
            <form method="get" action="/utilisateur-serie-magazine.php" class="collection-search magazine-issues-search">
                <input type="hidden" name="id" value="<?= $targetUserId ?>">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
                <?php if ($possessionFilter !== Moncine\MagazineRepository::POSSESSION_ALL): ?>
                    <input type="hidden" name="possession" value="<?= Moncine\View::escape($possessionFilter) ?>">
                <?php endif; ?>
                <label for="profile_mag_q">Rechercher un numéro</label>
                <input type="search" name="q" id="profile_mag_q"
                       value="<?= Moncine\View::escape($searchQuery) ?>"
                       placeholder="Numéro, date, mot du sommaire…">
                <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                <?php if ($hasSearch): ?>
                    <a href="<?= Moncine\View::escape(
                        Moncine\View::userProfileMagazineSeriesUrl($targetUserId, $seriesId, $listMode)
                    ) ?>" class="btn btn-secondary btn-sm">Effacer</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($issues === []): ?>
            <p class="hint">
                <?php if ($hasSearch): ?>
                    Aucun numéro ne correspond à votre recherche.
                <?php elseif ($isWishlist): ?>
                    Aucun numéro dans les envies pour cette série.
                <?php else: ?>
                    Aucun numéro référencé dans cette série.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="stats">
                <?= (int) $filteredCount ?> numéro<?= (int) $filteredCount > 1 ? 's' : '' ?>
                <?= $isWishlist ? 'en envies' : 'dans la collection' ?>.
            </p>
            <?php
            $paginationIdSuffix = '-top';
            require MONCINE_ROOT . '/templates/_user_public_magazine_issues_pagination.php';
            require MONCINE_ROOT . '/templates/_user_public_magazine_issues_grid.php';
            $paginationIdSuffix = '-bottom';
            require MONCINE_ROOT . '/templates/_user_public_magazine_issues_pagination.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
