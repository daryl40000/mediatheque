<?php
/**
 * Déposer une affiche catalogue depuis un fichier image (admin).
 *
 * @var int $oeuvreId
 * @var string $catalogueBackUrl
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var string $posterUploadError
 * @var bool $posterUploadOpen
 */
$posterUploadError = $posterUploadError ?? '';
$posterUploadOpen = $posterUploadOpen ?? false;
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = (int) ($catalogPage ?? 1);
$maxMo = (int) ceil(
    (defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 2_097_152) / 1024 / 1024
);
?>
<details class="film-edit-panel oeuvre-poster-upload"<?= $posterUploadOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Déposer une affiche (fichier image)</summary>

    <p class="hint">
        Choisissez une image sur votre ordinateur (JPEG, PNG ou WebP, <?= $maxMo ?> Mo max).
        Elle remplace l’affiche actuelle et est enregistrée dans <code>/posters/</code>.
    </p>

    <?php if ($posterUploadError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($posterUploadError) ?></div>
    <?php endif; ?>

    <form method="post" action="/upload-affiche-oeuvre.php" enctype="multipart/form-data" class="film-edit-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, $catalogPage) ?>">

        <label for="oeuvre_poster_file">Fichier image</label>
        <input type="file" name="poster_file" id="oeuvre_poster_file"
               accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>

        <button type="submit" class="btn btn-secondary">Enregistrer l’affiche</button>
    </form>
</details>
