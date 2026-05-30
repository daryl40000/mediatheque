<?php
/**
 * @var array<string, mixed>|null $profileUser
 * @var string $accessDenied
 * @var bool $isSelf
 * @var array<string, int> $stats
 * @var list<array<string, mixed>> $lastViewed
 * @var list<array<string, mixed>> $lastCollection
 * @var list<array<string, mixed>> $lastWishlist
 * @var list<array<string, mixed>> $listFilms
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
?>
<section class="account-page social-profile-page">
    <?php if ($accessDenied !== '' || $profileUser === null): ?>
        <h1>Profil utilisateur</h1>
        <p class="alert alert-warning"><?= Moncine\View::escape($accessDenied !== '' ? $accessDenied : 'Profil introuvable.') ?></p>
        <p class="collection-page__footer-links">
            <a href="/mes-amis.php">← Mes amis</a>
        </p>
    <?php elseif ($listMode !== ''): ?>
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
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId)) ?>">
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
        <?php else: ?>
            <?php
            $films = $listFilms ?? [];
            require MONCINE_ROOT . '/templates/_user_public_films_grid.php';
            ?>
        <?php endif; ?>
        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape(Moncine\View::userProfileUrl($targetUserId)) ?>">← Retour au profil</a>
            ·
            <a href="/mes-amis.php">Mes amis</a>
        </p>
    <?php else:
        $pseudo = trim((string) ($profileUser['pseudo'] ?? ''));
        $displayName = Moncine\UserProfile::displayName($profileUser);
        ?>
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

        <section class="social-profile-stats" aria-labelledby="social-stats-heading">
            <h2 id="social-stats-heading">Statistiques</h2>
            <dl class="social-profile-stats__list">
                <div>
                    <dt>Films en collection</dt>
                    <dd>
                        <a href="<?= Moncine\View::escape(
                            Moncine\View::userProfileListUrl($targetUserId, 'collection')
                        ) ?>">
                            <?= (int) ($stats['collection_count'] ?? 0) ?>
                        </a>
                    </dd>
                </div>
                <div>
                    <dt>Films dans les envies</dt>
                    <dd>
                        <a href="<?= Moncine\View::escape(
                            Moncine\View::userProfileListUrl($targetUserId, 'envies')
                        ) ?>">
                            <?= (int) ($stats['wishlist_count'] ?? 0) ?>
                        </a>
                    </dd>
                </div>
                <div>
                    <dt>Films déjà vus</dt>
                    <dd>
                        <a href="<?= Moncine\View::escape(
                            Moncine\View::userProfileListUrl($targetUserId, 'vus')
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
                                (int) ($stats['year'] ?? (int) date('Y'))
                            )
                        ) ?>">
                            <?= (int) ($stats['films_vus_year_count'] ?? 0) ?>
                        </a>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="social-profile-section" aria-labelledby="social-last-viewed-heading">
            <h2 id="social-last-viewed-heading">5 derniers films vus</h2>
            <?php
            $films = $lastViewed;
            $emptyHint = 'Aucune vision enregistrée pour le moment.';
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
        </section>

        <?php if ($isSelf || (int) ($stats['collection_count'] ?? 0) > 0): ?>
            <section class="social-profile-section" aria-labelledby="social-last-collection-heading">
                <h2 id="social-last-collection-heading">5 derniers ajouts à la collection</h2>
                <?php
                $films = $lastCollection ?? [];
                $emptyHint = 'Aucun film dans la collection pour le moment.';
                require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                ?>
            </section>
        <?php endif; ?>

        <section class="social-profile-section" aria-labelledby="social-last-wishlist-heading">
            <h2 id="social-last-wishlist-heading">5 derniers ajouts aux envies</h2>
            <?php
            $films = $lastWishlist;
            $emptyHint = 'Aucun film dans les envies pour le moment.';
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
        </section>

        <p class="collection-page__footer-links">
            <a href="/mes-amis.php">← Mes amis</a>
            <?php if ($isSelf): ?>
                ·
                <a href="/mon-compte.php">Mon compte</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>
