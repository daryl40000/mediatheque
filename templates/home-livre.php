<?php
/**
 * Page d’accueil — onglet Livres.
 *
 * @var int $bookCount
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
    <h1>Quel livre ouvrir ce soir ?</h1>
    <p class="lead">Organisez vos livres papier et numériques, et reliez les guides aux jeux concernés.</p>

    <?php if ((int) $bookCount === 0): ?>
        <div class="alert alert-info">
            <p><strong>Aucun livre en collection.</strong> Commencez par en ajouter un.</p>
            <a class="btn btn-primary" href="<?= Moncine\View::escape(Moncine\View::addLivreUrl()) ?>">Ajouter un livre</a>
            <a class="btn btn-secondary" href="/livres-envies.php"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a>
        </div>
    <?php else: ?>
        <p class="stats">
            <?= (int) $bookCount ?> livre<?= $bookCount > 1 ? 's' : '' ?> dans votre collection.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/livres.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a>
            <a class="btn btn-secondary" href="<?= Moncine\View::escape(Moncine\View::addLivreUrl()) ?>">Ajouter un livre</a>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) $bookCount > 0): ?>
    <section class="home-dashboard" aria-labelledby="home-livre-dashboard-heading">
        <h2 id="home-livre-dashboard-heading" class="home-dashboard__title">Votre activité récente</h2>

        <section class="social-profile-section" aria-labelledby="home-livre-collection-heading">
            <h2 id="home-livre-collection-heading">5 derniers ajouts à la collection</h2>
            <?php
            $films = $lastCollection;
            $emptyHint = 'Aucun livre dans la collection pour le moment.';
            $mediaDomain = Moncine\MediaDomain::LIVRE;
            $targetUserId = (int) ($currentUserId ?? 0);
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/livres.php">Voir mes livres</a></p>
        </section>

        <section class="social-profile-section" aria-labelledby="home-livre-wishlist-heading">
            <h2 id="home-livre-wishlist-heading">5 derniers ajouts aux envies</h2>
            <?php
            $films = $lastWishlist;
            $emptyHint = 'Aucune envie livre pour le moment.';
            $mediaDomain = Moncine\MediaDomain::LIVRE;
            $targetUserId = (int) ($currentUserId ?? 0);
            require MONCINE_ROOT . '/templates/_user_profile_poster_strip.php';
            ?>
            <p class="home-dashboard__more"><a href="/livres-envies.php">Voir mes envies livres</a></p>
        </section>
    </section>
<?php endif; ?>
