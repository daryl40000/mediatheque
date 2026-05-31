<?php
/** @var int $seriesCount */
/** @var int $issueCount */
/** @var int $wishlistCount */
$mediaNav = Moncine\MediaContext::navLabels();
?>
<section class="stats-page">
    <h1><?= Moncine\View::escape($mediaNav['stats']) ?></h1>
    <p class="lead">Vue d’ensemble de votre collection de magazines.</p>

    <div class="stats-grid">
        <article class="stat-card stat-card--highlight">
            <p class="stat-card__value"><?= (int) $seriesCount ?></p>
            <p class="stat-card__label">Séries en collection</p>
            <p class="stat-card__hint"><a href="/magazines.php">Voir la liste</a></p>
        </article>
        <article class="stat-card">
            <p class="stat-card__value"><?= (int) $issueCount ?></p>
            <p class="stat-card__label">Numéros possédés</p>
        </article>
        <?php if ((int) $wishlistCount > 0): ?>
            <article class="stat-card">
                <p class="stat-card__value"><?= (int) $wishlistCount ?></p>
                <p class="stat-card__label"><?= Moncine\View::escape($mediaNav['wishlist']) ?></p>
                <p class="stat-card__hint"><a href="/magazines-envies.php">Voir la liste</a></p>
            </article>
        <?php endif; ?>
    </div>
</section>
