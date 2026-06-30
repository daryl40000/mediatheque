<?php
/** @var bool $showChoice */
/** @var string $statut */
/** @var string $statutLabel */
/** @var array<string, string> $kindChoices */
/** @var array<string, string> $supportChoices */
/** @var list<string> $knownGenres */
/** @var bool $moduleAvailable */
/** @var string $saveError */
/** @var array<string, mixed>|null $prefillAlbum */
/** @var int $prefillOeuvreId */
?>
<section>
    <?php if ($showChoice): ?>
        <h1>Ajouter un album</h1>
        <p class="lead">Bande dessinée, manga ou comic — enregistré dans votre bibliothèque BD.</p>
        <p><a href="/bd.php" class="btn btn-secondary btn-sm">← Mes BD</a></p>

        <div class="collection-page__actions">
            <a href="/ajouter-bd.php?statut=collection" class="btn btn-accent">Dans ma collection</a>
            <a href="/ajouter-bd.php?statut=wishlist" class="btn btn-secondary">Dans mes envies</a>
        </div>
    <?php else: ?>
        <h1>Ajouter — <?= Moncine\View::escape($statutLabel) ?></h1>
        <p class="lead">
            Renseignez la série, le tome et les auteurs. Si une fiche existe déjà au catalogue partagé,
            vous pouvez la pré-remplir via un lien du type
            <code>/ajouter-bd.php?statut=<?= Moncine\View::escape($statut) ?>&amp;oeuvre_id=…</code>.
        </p>
        <p><a href="/ajouter-bd.php" class="btn btn-secondary btn-sm">← Changer de destination</a></p>

        <?php if (!$moduleAvailable): ?>
            <div class="alert alert-warning">Le module BD n’est pas encore disponible.</div>
        <?php endif; ?>

        <?php if ($saveError !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
        <?php endif; ?>

        <form method="post" action="/enregistrer-bd.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

            <?php
            $album = is_array($prefillAlbum ?? null) ? $prefillAlbum : null;
            require MONCINE_ROOT . '/templates/_bd_form_fields.php';
            ?>

            <button type="submit" class="btn btn-primary"<?= $moduleAvailable ? '' : ' disabled' ?>>Enregistrer</button>
        </form>
    <?php endif; ?>
</section>
