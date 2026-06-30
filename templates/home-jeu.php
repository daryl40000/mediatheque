<?php
/**
 * Page d’accueil — onglet jeux vidéo.
 *
 * @var int $gameCount
 * @var bool $setupDone
 * @var list<array<string, mixed>> $lastNoted
 * @var list<array<string, mixed>> $lastFinished
 * @var list<array<string, mixed>> $lastCollection
 * @var list<array<string, mixed>> $lastWishlist
 * @var int $currentUserId
 */
$mediaNav = Moncine\MediaContext::navLabels();
?>
<section class="hero">
    <?php if (!empty($setupDone)): ?>
        <p class="alert alert-success">Compte administrateur créé. Vous êtes connecté.</p>
    <?php endif; ?>
    <h1>Quel jeu ce soir ?</h1>
    <p class="lead">
        Retrouvez votre ludothèque, notez vos jeux et reliez-les aux tests et previews
        de vos magazines.
    </p>

    <?php if ((int) $gameCount === 0): ?>
        <div class="alert alert-info">
            <p><strong>Aucun jeu en collection.</strong> Commencez par ajouter un titre à votre bibliothèque.</p>
            <a class="btn btn-primary" href="/ajouter-jeu.php">Ajouter un jeu</a>
            <a class="btn btn-secondary" href="/jeux-envies.php"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a>
        </div>
    <?php else: ?>
        <p class="stats">
            <?= (int) $gameCount ?> jeu<?= $gameCount > 1 ? 'x' : '' ?> dans votre collection.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/jeux.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a>
            <a class="btn btn-secondary" href="/ajouter-jeu.php">Ajouter un jeu</a>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) $gameCount > 0): ?>
    <section class="home-dashboard" aria-labelledby="home-games-dashboard-heading">
        <h2 id="home-games-dashboard-heading" class="home-dashboard__title">Votre activité récente</h2>

        <section class="social-profile-section" aria-labelledby="home-last-noted-heading">
            <h2 id="home-last-noted-heading">5 derniers jeux notés</h2>
            <?php
            $films = $lastNoted;
            $emptyHint = 'Aucune note enregistrée pour le moment.';
            $linkToGame = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
        </section>

        <section class="social-profile-section" aria-labelledby="home-last-finished-heading">
            <h2 id="home-last-finished-heading">5 derniers jeux finis</h2>
            <?php
            $films = $lastFinished ?? [];
            $emptyHint = 'Aucun jeu marqué comme terminé pour le moment.';
            $linkToGame = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
        </section>

        <section class="social-profile-section" aria-labelledby="home-last-collection-heading">
            <h2 id="home-last-collection-heading">5 derniers ajouts à la collection</h2>
            <?php
            $films = $lastCollection;
            $emptyHint = 'Aucun jeu dans la collection pour le moment.';
            $linkToGame = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/jeux.php">Voir mes jeux</a></p>
        </section>

        <section class="social-profile-section" aria-labelledby="home-last-wishlist-heading">
            <h2 id="home-last-wishlist-heading">5 derniers ajouts aux envies</h2>
            <?php
            $films = $lastWishlist;
            $emptyHint = 'Aucun jeu dans les envies pour le moment.';
            $linkToGame = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/jeux-envies.php">Voir mes envies jeux</a></p>
        </section>
    </section>
<?php endif; ?>

<section class="home-dashboard">
    <h2 class="home-dashboard__title">Raccourcis</h2>
    <ul>
        <li><a href="/jeux.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a></li>
        <li><a href="/jeux-envies.php"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a></li>
        <li><a href="/ajouter-jeu.php">Ajouter un jeu</a></li>
        <?php if (Moncine\GameFranchiseRepository::isAvailable()): ?>
            <li><a href="/sagas-jeux.php">Sagas jeux</a></li>
        <?php endif; ?>
    </ul>
</section>
