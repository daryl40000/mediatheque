<?php
/** @var array<string, mixed> $series */
/** @var string $statut */
/** @var float $suggestNumeroOrdre */
/** @var string $publicationTypeLabel */
/** @var string $error */
/** @var array<string, mixed>|null $catalogIssue */
/** @var int $catalogOeuvreId */
$catalogIssue = $catalogIssue ?? null;
$catalogOeuvreId = (int) ($catalogOeuvreId ?? 0);
$prefillNumero = (string) ($catalogIssue['numero'] ?? ($_GET['numero'] ?? ''));
$prefillDate = (string) ($catalogIssue['date_parution'] ?? '');
$prefillOrdre = $catalogIssue !== null
    ? (float) ($catalogIssue['numero_ordre'] ?? $suggestNumeroOrdre)
    : $suggestNumeroOrdre;
$prefillHorsSerie = !empty($catalogIssue['est_hors_serie']);
$seriesId = (int) ($series['id'] ?? 0);
?>
<section>
    <h1>Ajouter un numéro</h1>
    <p class="lead">
        Série : <strong><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></strong>
        (<?= Moncine\View::escape($publicationTypeLabel) ?>)
    </p>
    <p class="hint">
        Tapez le numéro pour le retrouver au <strong>catalogue partagé</strong> s’il existe déjà,
        ou saisissez un nouveau numéro manuellement.
    </p>
    <p>
        <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl((int) ($series['id'] ?? 0))) ?>"
           class="btn btn-secondary btn-sm">← Retour à la série</a>
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>
    <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

    <form method="post" action="/enregistrer-numero-magazine.php" enctype="multipart/form-data" class="import-form"
          id="magazine-issue-add-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">
        <input type="hidden" name="statut" value="<?= Moncine\View::escape($statut) ?>">
        <input type="hidden" name="oeuvre_id" id="catalog_issue_oeuvre_id" value="<?= $catalogOeuvreId > 0 ? $catalogOeuvreId : '' ?>">

        <label for="numero">Numéro <span class="required">*</span></label>
        <div class="catalog-title-autocomplete magazine-issue-catalog-autocomplete"
             id="magazine-issue-catalog-autocomplete"
             data-magazine-issue-catalog-autocomplete
             data-search-url="/rechercher-numeros-catalogue.php?series_id=<?= $seriesId ?>"
             data-oeuvre-id-input="catalog_issue_oeuvre_id"
             data-numero-input="numero"
             data-date-input="date_parution"
             data-ordre-input="numero_ordre"
             data-hors-serie-input="est_hors_serie"
             data-hint-id="catalog_issue_hint">
            <input type="text" name="numero" id="numero" required
                   class="catalog-title-autocomplete__input"
                   placeholder="Ex. 123 ou HS 5"
                   value="<?= Moncine\View::escape($prefillNumero) ?>"
                   autocomplete="off"
                   aria-autocomplete="list"
                   aria-controls="magazine-issue-catalog-list"
                   aria-expanded="false">
            <ul id="magazine-issue-catalog-list" class="catalog-title-autocomplete__list" role="listbox" hidden></ul>
        </div>
        <p id="catalog_issue_hint" class="hint"<?= $catalogOeuvreId <= 0 ? ' hidden' : '' ?>>
            <?php if ($catalogOeuvreId > 0): ?>
                Numéro sélectionné au catalogue — vous pouvez ajouter papier / PDF sans recréer la fiche.
            <?php endif; ?>
        </p>

        <label for="numero_ordre">Ordre de tri</label>
        <input type="number" step="0.1" name="numero_ordre" id="numero_ordre"
               value="<?= Moncine\View::escape((string) $prefillOrdre) ?>">
        <p class="hint">Utilisé pour trier les numéros dans le tableau (123, 124… ; hors-série : 123.5).</p>

        <label for="date_parution">Date de parution</label>
        <input type="date" name="date_parution" id="date_parution"
               value="<?= Moncine\View::escape($prefillDate) ?>">
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
            <input type="checkbox" name="est_hors_serie" id="est_hors_serie" value="1"
                   <?= $prefillHorsSerie ? 'checked' : '' ?>>
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
