<?php
/**
 * @var string $saveError
 * @var int $pendingCount
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 */
?>
<section class="catalog-submission-page">
    <h1>Proposer un jeu au catalogue</h1>
    <p class="lead">
        Si un jeu n’existe pas encore dans le catalogue partagé, vous pouvez proposer sa fiche.
        Un administrateur l’examinera avant qu’elle soit visible pour tout le monde.
        Vous pouvez envoyer <strong>plusieurs propositions</strong> en parallèle.
    </p>
    <p class="hint">
        Vous proposez une <strong>fiche catalogue</strong> (titre, plateformes, studio…).
        Une fois acceptée, vous l’ajouterez à votre collection depuis
        <a href="/ajouter-jeu.php">Ajouter un jeu</a>.
        · <a href="/proposer-oeuvre.php">Proposer un film</a>
    </p>

    <?php if ($pendingCount > 0): ?>
        <p class="alert alert-info">
            Vous avez <?= (int) $pendingCount ?> proposition<?= $pendingCount > 1 ? 's' : '' ?>
            <strong>en attente</strong>.
            <a href="/mes-soumissions.php">Voir mes propositions</a>
        </p>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
    <?php endif; ?>

    <?php
    $formAction = '/enregistrer-soumission.php';
    $fieldPrefix = 'propose_game';
    $game = [];
    $userNote = '';
    $showUserNote = true;
    $submitLabel = 'Envoyer la proposition';
    $cancelUrl = '/jeux.php';
    $hiddenFields = [];
    require MONCINE_ROOT . '/templates/_game_catalog_submission_form.php';
    ?>
</section>
