<?php
/** @var array<string, mixed> $series */
/** @var string $statut */
/** @var int $suggestTomeNumero */
/** @var string $kindLabel */
/** @var array<string, string> $supportChoices */
/** @var list<string> $knownGenres */
/** @var string $error */
/** @var array<string, mixed>|null $prefillAlbum */
/** @var int $prefillOeuvreId */
/** @var bool $moduleAvailable */
$seriesId = (int) ($series['id'] ?? 0);
?>
<section>
    <h1>Ajouter un tome</h1>
    <p class="lead">
        Série : <strong><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></strong>
        (<?= Moncine\View::escape($kindLabel) ?>)
    </p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::bdSeriesUrl($seriesId, 'tome', 'asc', ['statut' => $statut])) ?>"
           class="btn btn-secondary btn-sm">← Retour à la série</a>
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/enregistrer-bd.php" enctype="multipart/form-data" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

        <?php
        $album = is_array($prefillAlbum ?? null) ? $prefillAlbum : null;
        require MONCINE_ROOT . '/templates/_bd_form_fields.php';
        ?>

        <?php require MONCINE_ROOT . '/templates/_bd_cover_fields.php'; ?>

        <button type="submit" class="btn btn-primary"<?= $moduleAvailable ? '' : ' disabled' ?>>Enregistrer le tome</button>
    </form>
</section>
