<?php
/**
 * Page d’accueil — onglet BD / manga.
 *
 * @var int $albumCount
 * @var bool $setupDone
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
    <h1>Quelle BD lire ce soir ?</h1>
    <p class="lead">Organisez vos séries BD et mangas, puis ajoutez les tomes un par un.</p>

    <?php if ((int) $albumCount === 0): ?>
        <div class="alert alert-info">
            <p><strong>Aucune série en collection.</strong> Commencez par créer une série.</p>
            <a class="btn btn-primary" href="/ajouter-serie-bd.php">Ajouter une série</a>
            <a class="btn btn-secondary" href="/bd-envies.php"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a>
        </div>
    <?php else: ?>
        <p class="stats">
            <?= (int) $albumCount ?> série<?= $albumCount > 1 ? 's' : '' ?> dans votre collection.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/bd.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a>
            <a class="btn btn-secondary" href="/ajouter-serie-bd.php">Ajouter une série</a>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) $albumCount > 0): ?>
    <section class="home-dashboard" aria-labelledby="home-bd-dashboard-heading">
        <h2 id="home-bd-dashboard-heading" class="home-dashboard__title">Votre activité récente</h2>

        <section class="social-profile-section" aria-labelledby="home-bd-collection-heading">
            <h2 id="home-bd-collection-heading">5 derniers ajouts à la collection</h2>
            <?php
            $films = $lastCollection;
            $emptyHint = 'Aucun album dans la collection pour le moment.';
            $linkToBd = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/bd.php">Voir mes BD</a></p>
        </section>

        <section class="social-profile-section" aria-labelledby="home-bd-wishlist-heading">
            <h2 id="home-bd-wishlist-heading">5 derniers ajouts aux envies</h2>
            <?php
            $films = $lastWishlist;
            $emptyHint = 'Aucune envie BD pour le moment.';
            $linkToBd = true;
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/bd-envies.php">Voir mes envies BD</a></p>
        </section>
    </section>
<?php endif; ?>
