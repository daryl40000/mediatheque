<?php
/** @var bool $showChoice */
/** @var string $statut */
/** @var string $statutLabel */
/** @var array<string, string> $platformChoices */
/** @var list<string> $knownGenres */
/** @var bool $moduleAvailable */
/** @var string $saveError */
?>
<section>
    <?php if ($showChoice): ?>
        <h1>Ajouter un jeu</h1>
        <p class="lead">Le jeu sera enregistré dans le catalogue partagé, puis ajouté à votre bibliothèque.</p>
        <p><a href="/jeux.php" class="btn btn-secondary btn-sm">← Mes jeux</a></p>

        <div class="collection-page__actions">
            <a href="/ajouter-jeu.php?statut=collection" class="btn btn-accent">Dans ma collection</a>
            <a href="/ajouter-jeu.php?statut=wishlist" class="btn btn-secondary">Dans mes envies</a>
        </div>
    <?php else: ?>
        <h1>Ajouter — <?= Moncine\View::escape($statutLabel) ?></h1>
        <p class="lead">
            Tapez le titre du jeu : si une fiche existe déjà au catalogue partagé, choisissez-la dans la liste.
            Sinon, complétez le formulaire pour créer une nouvelle fiche.
        </p>
        <p><a href="/ajouter-jeu.php" class="btn btn-secondary btn-sm">← Changer de destination</a></p>

        <?php if (!$moduleAvailable): ?>
            <div class="alert alert-warning">Le module jeux n’est pas encore disponible. Rechargez la page dans quelques secondes.</div>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
        <?php endif; ?>

        <form method="post" action="/enregistrer-jeu.php" class="import-form" enctype="multipart/form-data"
              data-game-catalog-url="/rechercher-jeux-catalogue.php">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

            <?php
            $game = is_array($prefillGame ?? null) ? $prefillGame : null;
            $useCatalogAutocomplete = $moduleAvailable;
            require MONCINE_ROOT . '/templates/_game_form_fields.php';
            ?>

            <button type="submit" class="btn btn-primary"<?= $moduleAvailable ? '' : ' disabled' ?>>Enregistrer</button>
        </form>
    <?php endif; ?>
</section>
