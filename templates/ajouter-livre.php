<?php
/**
 * Ajouter un livre.
 *
 * @var bool $showChoice
 * @var string $statut
 * @var string $statutLabel
 * @var bool $moduleAvailable
 * @var string $saveError
 * @var array<string, mixed> $book
 * @var list<string> $knownCategories
 */
$linkedGames = [];
?>
<section>
    <?php if ($showChoice): ?>
        <h1>Ajouter un livre</h1>
        <p class="lead">Le livre sera enregistré dans le catalogue, puis ajouté à votre bibliothèque.</p>
        <p><a href="/livres.php" class="btn btn-secondary btn-sm">← Mes livres</a></p>

        <div class="collection-page__actions">
            <a href="/ajouter-livre.php?statut=collection" class="btn btn-accent">Dans ma collection</a>
            <a href="/ajouter-livre.php?statut=wishlist" class="btn btn-secondary">Dans mes envies</a>
        </div>
    <?php else: ?>
        <h1>Ajouter — <?= Moncine\View::escape($statutLabel) ?></h1>
        <p class="lead">Renseignez les informations du livre. Les catégories aident à classer votre collection.</p>
        <p><a href="/ajouter-livre.php" class="btn btn-secondary btn-sm">← Changer de destination</a></p>

        <?php if (!$moduleAvailable): ?>
            <div class="alert alert-warning">Le module livres n’est pas encore disponible. Rechargez la page dans quelques secondes.</div>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
        <?php endif; ?>

        <form method="post" action="/enregistrer-livre.php" class="import-form" enctype="multipart/form-data"
              data-livre-form>
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

            <?php require MONCINE_ROOT . '/templates/_livre_form_fields.php'; ?>

            <button type="submit" class="btn btn-primary"<?= $moduleAvailable ? '' : ' disabled' ?>>Enregistrer</button>
        </form>
    <?php endif; ?>
</section>
