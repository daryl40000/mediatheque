<?php
/**
 * @var string $saveError
 * @var int $pendingCount
 */
?>
<section class="catalog-submission-page">
    <h1>Proposer une œuvre au catalogue</h1>
    <p class="lead">
        Si un film n’existe pas encore dans le catalogue Moncine, vous pouvez proposer sa fiche.
        Un administrateur l’examinera avant qu’elle soit visible pour tout le monde.
        Vous pouvez envoyer <strong>plusieurs propositions</strong> en parallèle.
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
    $fieldPrefix = 'propose';
    $film = [];
    $userNote = '';
    $showUserNote = true;
    $submitLabel = 'Envoyer la proposition';
    $cancelUrl = '/films.php';
    $hiddenFields = [];
    require MONCINE_ROOT . '/templates/_catalog_submission_form.php';
    ?>
</section>
