<?php
/** @var array<string, mixed> $series */
/** @var string $statut */
/** @var float $suggestNumeroOrdre */
/** @var string $publicationTypeLabel */
/** @var string $error */
?>
<section>
    <h1>Ajouter un numéro</h1>
    <p class="lead">
        Série : <strong><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></strong>
        (<?= Moncine\View::escape($publicationTypeLabel) ?>)
    </p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl((int) ($series['id'] ?? 0))) ?>"
           class="btn btn-secondary btn-sm">← Retour à la série</a>
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>
    <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

    <form method="post" action="/enregistrer-numero-magazine.php" enctype="multipart/form-data" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="series_id" value="<?= (int) ($series['id'] ?? 0) ?>">
        <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">

        <label for="numero">Numéro <span class="required">*</span></label>
        <input type="text" name="numero" id="numero" required
               placeholder="Ex. 123 ou HS 5"
               value="<?= Moncine\View::escape((string) ($_GET['numero'] ?? '')) ?>">

        <label for="numero_ordre">Ordre de tri</label>
        <input type="number" step="0.1" name="numero_ordre" id="numero_ordre"
               value="<?= Moncine\View::escape((string) $suggestNumeroOrdre) ?>">
        <p class="hint">Utilisé pour trier les numéros dans le tableau (123, 124… ; hors-série : 123.5).</p>

        <label for="date_parution">Date de parution</label>
        <input type="date" name="date_parution" id="date_parution">
        <p class="hint">Affichée en <?= Moncine\View::escape(strtolower($publicationTypeLabel)) ?> sur la liste des numéros.</p>

        <label for="pages">Nombre de pages</label>
        <input type="number" name="pages" id="pages" min="0" value="0">
        <?php if (Moncine\MagazinePdfInfo::isAvailable()): ?>
            <p class="hint">Laissez 0 pour détecter le nombre de pages depuis le PDF importé.</p>
        <?php endif; ?>

        <fieldset class="magazine-support-fieldset">
            <legend>Support</legend>
            <label class="checkbox">
                <input type="checkbox" name="support_papier" value="1">
                J’ai le numéro en <strong>papier</strong>
            </label>
            <p class="hint">Le tag <span class="magazine-tag magazine-tag--pdf">PDF</span> sera ajouté automatiquement si vous importez un fichier.</p>
        </fieldset>

        <label class="checkbox">
            <input type="checkbox" name="est_hors_serie" value="1">
            Hors-série / numéro spécial
        </label>

        <label for="sommaire">Sommaire</label>
        <textarea name="sommaire" id="sommaire" rows="8"
                  placeholder="Rubriques, articles principaux, pages…"></textarea>

        <label for="cover_file">Couverture (JPEG, PNG, WebP)</label>
        <input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
        <p class="hint">Taille max. <?= Moncine\View::escape(Moncine\UploadLimits::maxPosterBytesLabel()) ?> (comme les affiches films).</p>

        <label for="pdf_file">Fichier PDF du numéro (optionnel, max <?= Moncine\View::escape(Moncine\UploadLimits::maxPdfBytesLabel()) ?>)</label>
        <input type="file" name="pdf_file" id="pdf_file" accept="application/pdf,.pdf">
        <p class="hint">Les scans complets peuvent être volumineux. Limite serveur PHP :
            upload <?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?>,
            post <?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?>.</p>

        <button type="submit" class="btn btn-primary">
            <?= $statut === Moncine\LibraryStatut::WISHLIST ? 'Ajouter à mes envies' : 'Ajouter à ma collection' ?>
        </button>
    </form>
</section>
