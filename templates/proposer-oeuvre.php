<?php
/**
 * @var string $saveError
 * @var bool $hasPending
 */
?>
<section class="catalog-submission-page">
    <h1>Proposer une œuvre au catalogue</h1>
    <p class="lead">
        Si un film n’existe pas encore dans le catalogue Moncine, vous pouvez proposer sa fiche.
        Un administrateur l’examinera avant qu’elle soit visible pour tout le monde.
    </p>

    <?php if ($hasPending): ?>
        <p class="alert alert-info">
            Vous avez déjà une proposition <strong>en attente</strong>.
            <a href="/mes-soumissions.php">Voir mes propositions</a>
        </p>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
    <?php endif; ?>

    <?php if (!$hasPending): ?>
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
    <?php else: ?>
        <p><a href="/mes-soumissions.php" class="btn btn-secondary">Mes propositions</a></p>
    <?php endif; ?>
</section>
