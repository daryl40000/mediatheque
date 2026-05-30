<?php
/**
 * @var int $filmCount
 * @var bool $setupDone
 * @var list<array<string, mixed>> $lastViewed
 * @var list<array<string, mixed>> $lastCollection
 * @var list<array<string, mixed>> $lastWishlist
 * @var string $profileUrl
 * @var int $currentUserId
 */
?>
<section class="hero">
    <?php if (!empty($setupDone)): ?>
        <p class="alert alert-success">Compte administrateur créé. Vous êtes connecté.</p>
    <?php endif; ?>
    <h1>Quel film ce soir ?</h1>
    <p class="lead">
        Moncine vous aide à choisir parmi votre dvdthèque, comme un petit questionnaire,
        en tenant compte des films déjà vus.
    </p>

    <?php if ($filmCount === 0): ?>
        <div class="alert alert-info">
            <p><strong>Aucun film en base.</strong> Commencez par importer votre liste (CSV exporté depuis Excel).</p>
            <a class="btn btn-primary" href="/import.php">Importer ma dvdthèque</a>
            <a class="btn btn-secondary" href="<?= Moncine\View::escape(Moncine\View::addFilmChoiceUrl()) ?>">Ajouter un film</a>
        </div>
    <?php else: ?>
        <p class="stats"><?= (int) $filmCount ?> film<?= $filmCount > 1 ? 's' : '' ?> dans vos films.</p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/quiz.php">Lancer le questionnaire</a>
            <a class="btn btn-secondary" href="<?= Moncine\View::escape(Moncine\View::addFilmChoiceUrl()) ?>">Ajouter film</a>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) $filmCount > 0): ?>
    <section class="home-dashboard" aria-labelledby="home-dashboard-heading">
        <h2 id="home-dashboard-heading" class="home-dashboard__title">Votre activité récente</h2>

        <section class="social-profile-section" aria-labelledby="home-last-viewed-heading">
            <h2 id="home-last-viewed-heading">5 derniers films vus</h2>
                <?php
                $films = $lastViewed;
                $emptyHint = 'Aucune vision enregistrée pour le moment.';
                $linkToFilm = true;
                require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                ?>
                <?php if ($currentUserId > 0 && $lastViewed !== []): ?>
                    <p class="home-dashboard__more">
                        <a href="<?= Moncine\View::escape(Moncine\View::userProfileListUrl($currentUserId, 'vus')) ?>">
                            Voir tout l’historique
                        </a>
                    </p>
                <?php endif; ?>
        </section>

        <section class="social-profile-section" aria-labelledby="home-last-collection-heading">
            <h2 id="home-last-collection-heading">5 derniers ajouts à la collection</h2>
                <?php
                $films = $lastCollection;
                $emptyHint = 'Aucun film dans la collection pour le moment.';
                $linkToFilm = true;
                require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                ?>
            <p class="home-dashboard__more"><a href="/films.php">Voir mes films</a></p>
        </section>

        <section class="social-profile-section" aria-labelledby="home-last-wishlist-heading">
            <h2 id="home-last-wishlist-heading">5 derniers ajouts aux envies</h2>
                <?php
                $films = $lastWishlist;
                $emptyHint = 'Aucun film dans les envies pour le moment.';
                $linkToFilm = true;
                require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
                ?>
            <p class="home-dashboard__more"><a href="/souhaits.php">Voir mes envies</a></p>
        </section>
    </section>
<?php endif; ?>
