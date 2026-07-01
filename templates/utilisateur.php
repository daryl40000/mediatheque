<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var bool $isSelf
 * @var string $profileDomain
 * @var array{collection: string, wishlist: string, stats: string, footer: string} $profileNav
 * @var bool $profileDomainImplemented
 * @var array<string, int> $stats
 * @var list<array<string, mixed>> $lastViewed
 * @var list<array<string, mixed>> $lastNoted
 * @var list<array<string, mixed>> $lastCollection
 * @var list<array<string, mixed>> $lastWishlist
 * @var list<array<string, mixed>> $listFilms
 * @var list<array<string, mixed>> $listGames
 * @var list<array<string, mixed>> $listMagazineSeries
 * @var list<array<string, mixed>> $listBdSeries
 * @var list<array<string, mixed>> $listViewings
 * @var string $listMode
 * @var int|null $yearFilter
 * @var string $listTitle
 * @var int $targetUserId
 * @var string $sortBy
 * @var string $sortDir
 * @var int $viewerId
 * @var bool $areFriends
 */
use Moncine\MediaDomain;

$profileDomain = MediaDomain::normalize($profileDomain ?? MediaDomain::FILM);
$profileNav = $profileNav ?? MediaDomain::navLabels($profileDomain);
$isMagazineProfile = MediaDomain::isMagazine($profileDomain);
$isGameProfile = MediaDomain::isGame($profileDomain);
$isBdProfile = MediaDomain::isBd($profileDomain);
$isFilmProfile = !$isMagazineProfile && !$isGameProfile && !$isBdProfile;
$profileDomainImplemented = !empty($profileDomainImplemented);
?>
<section class="account-page social-profile-page">
    <?php if ($accessDenied !== '' || $profileUser === null): ?>
        <h1>Profil utilisateur</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Profil introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="/mes-amis.php">← Mes amis</a>
        </p>
    <?php elseif ($listMode !== ''): ?>
        <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>
        <?php if (!empty($_GET['pret'])): ?>
            <?php if ((string) $_GET['pret'] === 'demande'): ?>
                <p class="alert alert-success">Demande de prêt envoyée.</p>
            <?php elseif ((string) $_GET['pret'] === 'annule'): ?>
                <p class="alert alert-success">Demande de prêt annulée.</p>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($_GET['pret_erreur'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['pret_erreur']) ?></p>
        <?php endif; ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, $profileDomain)) ?>">
                <?= Moncine\View::escape(Moncine\UserProfile::displayName($profileUser)) ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($listTitle) ?></span>
        </p>
        <h1><?= Moncine\View::escape($listTitle) ?></h1>
        <p class="hint">Liste en lecture seule.</p>
        <?php if ($listMode === 'vus'): ?>
            <?php
            $viewings = $listViewings ?? [];
            require MONCINE_ROOT . '/templates/_user_public_viewings_list.php';
            ?>
        <?php elseif ($isMagazineProfile): ?>
            <?php require MONCINE_ROOT . '/templates/_user_public_magazine_series_grid.php'; ?>
        <?php elseif ($isBdProfile): ?>
            <?php
            $listBdSeries = $listBdSeries ?? [];
            require MONCINE_ROOT . '/templates/_user_public_bd_series_grid.php';
            ?>
        <?php elseif ($isGameProfile): ?>
            <?php
            $games = $listGames ?? [];
            require MONCINE_ROOT . '/templates/_user_public_games_grid.php';
            ?>
        <?php else: ?>
            <?php
            $films = $listFilms ?? [];
            require MONCINE_ROOT . '/templates/_user_public_films_grid.php';
            ?>
        <?php endif; ?>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId, $profileDomain)) ?>">← Retour au profil</a>
            ·
            <a href="/mes-amis.php">Mes amis</a>
        </p>
    <?php else:
        $pseudo = trim((string) ($profileUser['pseudo'] ?? ''));
        $displayName = Moncine\UserProfile::displayName($profileUser);
        ?>
        <?php require MONCINE_ROOT . '/templates/_user_profile_domain_tabs.php'; ?>

        <header class="social-profile-header">
            <h1><?= Moncine\View::escape($displayName) ?></h1>
            <?php if ($pseudo !== '' && $pseudo !== $displayName): ?>
                <p class="social-profile-header__legal hint">
                    <?= Moncine\View::escape(trim(
                        (string) ($profileUser['prenom'] ?? '') . ' ' . (string) ($profileUser['nom'] ?? '')
                    )) ?>
                </p>
            <?php endif; ?>
            <?php if ($isSelf): ?>
                <p class="hint">C’est votre profil tel que vos amis et votre groupe le voient.</p>
            <?php endif; ?>
            <?php if (trim((string) ($profileUser['ville'] ?? '')) !== ''): ?>
                <p class="social-profile-header__ville"><?= Moncine\View::escape((string) $profileUser['ville']) ?></p>
            <?php endif; ?>
        </header>

        <?php if (!$profileDomainImplemented): ?>
            <section class="media-domain-soon">
                <p class="lead">
                    La consultation du profil pour les <strong><?= Moncine\View::escape(MediaDomain::label($profileDomain)) ?></strong>
                    n’est pas encore disponible.
                </p>
                <p class="hint">Utilisez les onglets Films, Jeux, Magazines ou BD pour voir ce que cette personne partage déjà.</p>
            </section>
        <?php else: ?>
            <section class="social-profile-stats" aria-labelledby="social-stats-heading">
                <h2 id="social-stats-heading">Statistiques — <?= Moncine\View::escape(MediaDomain::label($profileDomain)) ?></h2>
                <dl class="social-profile-stats__list">
                    <div>
                        <dt>
                            <?php if ($isMagazineProfile): ?>
                                Séries en collection
                            <?php elseif ($isBdProfile): ?>
                                Séries en collection
                            <?php elseif ($isGameProfile): ?>
                                Jeux en collection
                            <?php else: ?>
                                Films en collection
                            <?php endif; ?>
                        </dt>
                        <dd>
                            <a href="<?= Moncine\View::escape(
                                Moncine\View::userProfileListUrl($targetUserId, 'collection', 'titre', 'titre', 'asc', null, $profileDomain)
                            ) ?>">
                                <?= (int) ($stats['collection_count'] ?? 0) ?>
                            </a>
                        </dd>
                    </div>
                    <?php if ($isMagazineProfile && (int) ($stats['issue_count'] ?? 0) > 0): ?>
                        <div>
                            <dt>Numéros possédés</dt>
                            <dd><?= (int) ($stats['issue_count'] ?? 0) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($isBdProfile && (int) ($stats['tome_count'] ?? 0) > 0): ?>
                        <div>
                            <dt>Tomes possédés</dt>
                            <dd><?= (int) ($stats['tome_count'] ?? 0) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt>
                            <?php if ($isMagazineProfile): ?>
                                Séries dans les envies
                            <?php elseif ($isBdProfile): ?>
                                Séries dans les envies
                            <?php elseif ($isGameProfile): ?>
                                Jeux dans les envies
                            <?php else: ?>
                                Films dans les envies
                            <?php endif; ?>
                        </dt>
                        <dd>
                            <a href="<?= Moncine\View::escape(
                                Moncine\View::userProfileListUrl($targetUserId, 'envies', 'titre', 'titre', 'asc', null, $profileDomain)
                            ) ?>">
                                <?= (int) ($stats['wishlist_count'] ?? 0) ?>
                            </a>
                        </dd>
                    </div>
                    <?php if ($isFilmProfile): ?>
                        <div>
                            <dt>Films déjà vus</dt>
                            <dd>
                                <a href="<?= Moncine\View::escape(
                                    Moncine\View::userProfileListUrl($targetUserId, 'vus', 'date', 'date', 'desc', null, $profileDomain)
                                ) ?>">
                                    <?= (int) ($stats['films_vus_count'] ?? 0) ?>
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt>Films vus en <?= (int) ($stats['year'] ?? (int) date('Y')) ?></dt>
                            <dd>
                                <a href="<?= Moncine\View::escape(
                                    Moncine\View::userProfileListUrl(
                                        $targetUserId,
                                        'vus',
                                        'date',
                                        'date',
                                        'desc',
                                        (int) ($stats['year'] ?? (int) date('Y')),
                                        $profileDomain
                                    )
                                ) ?>">
                                    <?= (int) ($stats['films_vus_year_count'] ?? 0) ?>
                                </a>
                            </dd>
                        </div>
                    <?php elseif ($isGameProfile): ?>
                        <div>
                            <dt>Jeux notés</dt>
                            <dd><?= (int) ($stats['games_noted_count'] ?? 0) ?></dd>
                        </div>
                        <div>
                            <dt>Jeux notés en <?= (int) ($stats['year'] ?? (int) date('Y')) ?></dt>
                            <dd><?= (int) ($stats['games_noted_year_count'] ?? 0) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <?php if ($isFilmProfile): ?>
                <section class="social-profile-section" aria-labelledby="social-last-viewed-heading">
                    <h2 id="social-last-viewed-heading">5 derniers films vus</h2>
                    <?php
                    $films = $lastViewed;
                    $emptyHint = 'Aucune vision enregistrée pour le moment.';
                    require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                    ?>
                </section>
            <?php elseif ($isGameProfile): ?>
                <section class="social-profile-section" aria-labelledby="social-last-noted-heading">
                    <h2 id="social-last-noted-heading">5 derniers jeux notés</h2>
                    <?php
                    $films = $lastNoted ?? [];
                    $emptyHint = 'Aucune note enregistrée pour le moment.';
                    $linkToGame = true;
                    require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                    ?>
                </section>
            <?php endif; ?>

            <?php if ($isSelf || (int) ($stats['collection_count'] ?? 0) > 0): ?>
                <section class="social-profile-section" aria-labelledby="social-last-collection-heading">
                    <h2 id="social-last-collection-heading">
                        <?php if ($isMagazineProfile): ?>
                            5 derniers numéros ajoutés à la collection
                        <?php elseif ($isBdProfile): ?>
                            5 derniers tomes ajoutés à la collection
                        <?php elseif ($isGameProfile): ?>
                            5 derniers jeux ajoutés à la collection
                        <?php else: ?>
                            5 derniers ajouts à la collection
                        <?php endif; ?>
                    </h2>
                    <?php if ($isMagazineProfile): ?>
                        <?php
                        $issuesList = $lastCollection ?? [];
                        $emptyHint = 'Aucun numéro en collection pour le moment.';
                        require MONCINE_ROOT . '/templates/_user_profile_magazine_issues_strip.php';
                        ?>
                    <?php elseif ($isBdProfile): ?>
                        <?php
                        $tomesList = $lastCollection ?? [];
                        $emptyHint = 'Aucun tome en collection pour le moment.';
                        require MONCINE_ROOT . '/templates/_user_profile_bd_tomes_strip.php';
                        ?>
                    <?php else: ?>
                        <?php
                        $films = $lastCollection ?? [];
                        $emptyHint = $isGameProfile
                            ? 'Aucun jeu dans la collection pour le moment.'
                            : 'Aucun film dans la collection pour le moment.';
                        $linkToGame = $isGameProfile;
                        require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                        ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="social-profile-section" aria-labelledby="social-last-wishlist-heading">
                <h2 id="social-last-wishlist-heading">
                    <?php if ($isMagazineProfile): ?>
                        5 derniers numéros ajoutés aux envies
                    <?php elseif ($isBdProfile): ?>
                        5 derniers tomes ajoutés aux envies
                    <?php elseif ($isGameProfile): ?>
                        5 derniers jeux ajoutés aux envies
                    <?php else: ?>
                        5 derniers ajouts aux envies
                    <?php endif; ?>
                </h2>
                <?php if ($isMagazineProfile): ?>
                    <?php
                    $issuesList = $lastWishlist ?? [];
                    $emptyHint = 'Aucun numéro dans les envies pour le moment.';
                    require MONCINE_ROOT . '/templates/_user_profile_magazine_issues_strip.php';
                    ?>
                <?php elseif ($isBdProfile): ?>
                    <?php
                    $tomesList = $lastWishlist ?? [];
                    $emptyHint = 'Aucun tome dans les envies pour le moment.';
                    require MONCINE_ROOT . '/templates/_user_profile_bd_tomes_strip.php';
                    ?>
                <?php else: ?>
                    <?php
                    $films = $lastWishlist;
                    $emptyHint = $isGameProfile
                        ? 'Aucun jeu dans les envies pour le moment.'
                        : 'Aucun film dans les envies pour le moment.';
                    $linkToGame = $isGameProfile;
                    require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                    ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <p class="collection-page__footer-links">
            <a href="/mes-amis.php">← Mes amis</a>
            <?php if ($isSelf): ?>
                ·
                <a href="/mon-compte.php">Mon compte</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
