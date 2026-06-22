<?php
/** @var int $seriesCount */
/** @var int $issueCount */
/** @var bool $setupDone */
$mediaNav = Moncine\MediaContext::navLabels();
?>
<section class="hero">
    <?php if (!empty($setupDone)): ?>
        <p class="alert alert-success">Compte administrateur créé. Vous êtes connecté.</p>
    <?php endif; ?>
    <h1><?= Moncine\View::escape($mediaNav['collection']) ?></h1>
    <p class="lead">
        Gérez vos revues par <strong>série</strong> (PC Jeux, Joystick, Warhammer…),
        puis ajoutez les numéros un par un avec couverture, sommaire et PDF.
    </p>

    <?php if ((int) $seriesCount === 0): ?>
        <div class="alert alert-info">
            <p><strong>Aucune série en collection.</strong> Commencez par créer une revue, puis ajoutez vos numéros.</p>
            <a class="btn btn-primary" href="/ajouter-serie-magazine.php">Ajouter une série</a>
        </div>
    <?php else: ?>
        <p class="stats">
            <?= (int) $seriesCount ?> série<?= $seriesCount > 1 ? 's' : '' ?>
            · <?= (int) $issueCount ?> numéro<?= $issueCount > 1 ? 's' : '' ?> possédé<?= $issueCount > 1 ? 's' : '' ?>
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="/magazines.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a>
            <a class="btn btn-secondary" href="/ajouter-serie-magazine.php">Ajouter une série</a>
        </div>
    <?php endif; ?>
</section>

<section class="home-dashboard">
    <h2 class="home-dashboard__title">Raccourcis</h2>
    <ul>
        <li><a href="/magazines.php"><?= Moncine\View::escape($mediaNav['collection']) ?></a></li>
        <li><a href="/magazines-envies.php"><?= Moncine\View::escape($mediaNav['wishlist']) ?></a></li>
        <li><a href="/ajouter-serie-magazine.php">Ajouter une série</a></li>
    </ul>
</section>
