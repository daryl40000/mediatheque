<?php
/**
 * Modifier un livre.
 *
 * @var array<string, mixed> $book
 * @var int $bookId
 * @var list<array<string, mixed>> $linkedGames
 * @var list<string> $knownCategories
 * @var string $saveError
 * @var bool $saved
 */
?>
<section>
    <h1>Modifier — <?= Moncine\View::escape((string) ($book['titre'] ?? '')) ?></h1>
    <p><a href="<?= Moncine\View::escape(Moncine\View::livreUrl($bookId)) ?>" class="btn btn-secondary btn-sm">← Retour à la fiche</a></p>

    <?php if (!empty($saved)): ?>
        <div class="alert alert-success">Modifications enregistrées.</div>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-modification-livre.php" class="import-form" enctype="multipart/form-data"
          data-livre-form>
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="book_id" value="<?= (int) $bookId ?>">

        <?php require MONCINE_ROOT . '/templates/_livre_form_fields.php'; ?>

        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>
</section>
