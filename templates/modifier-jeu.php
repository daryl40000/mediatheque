<?php
/** @var array<string, mixed> $game */
/** @var array<string, string> $platformChoices */
/** @var list<string> $knownGenres */
/** @var string $saveError */
/** @var bool $saved */
?>
<section>
    <h1>Modifier — <?= Moncine\View::escape((string) ($game['titre'] ?? 'Jeu')) ?></h1>
    <p class="lead">Mise à jour de la fiche catalogue (réservée aux administrateurs).</p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::gameUrl((int) ($game['id'] ?? 0))) ?>" class="btn btn-secondary btn-sm">← Fiche jeu</a>
    </p>

    <?php if ($saved): ?>
        <div class="alert alert-success">Fiche mise à jour.</div>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-jeu.php" class="import-form" enctype="multipart/form-data">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="bib_id" value="<?= (int) ($game['id'] ?? 0) ?>">

        <?php require MONCINE_ROOT . '/templates/_game_form_fields.php'; ?>

        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>
</section>
