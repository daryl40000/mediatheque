<?php
/** @var array<string, mixed>|null $issue */
/** @var bool $saved */
/** @var string $error */
/** @var string $dateLabel */
/** @var string $pdfUrl */
?>
<section>
    <?php if ($issue === null): ?>
        <h1>Numéro introuvable</h1>
        <p><a href="/magazines.php">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $bibId = (int) ($issue['bib_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $cover = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
        $pageStatut = (string) ($issue['statut'] ?? Moncine\LibraryStatut::COLLECTION);
        $isWishlistIssue = $pageStatut === Moncine\LibraryStatut::WISHLIST;
        $issuePageUrl = Moncine\View::magazineIssueUrl($bibId);
        $deleteMode = isset($_GET['supprimer']);
        $deleteModeUrl = $issuePageUrl . '&supprimer=1';
        ?>
        <div class="magazine-issue-toolbar">
            <p class="magazine-issue-toolbar__back">
                <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', ['statut' => $pageStatut])) ?>" class="btn btn-secondary btn-sm">
                    ← <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Série')) ?>
                </a>
            </p>
            <?php if (!$deleteMode): ?>
                <a href="<?= Moncine\View::escape($deleteModeUrl) ?>"
                   class="btn btn-icon btn-danger-text magazine-issue-toolbar__delete-toggle"
                   title="Mode suppression"
                   aria-label="Activer le mode suppression pour retirer ce numéro">
                    <svg class="icon-trash" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($deleteMode): ?>
            <div class="magazine-delete-mode-panel" role="region" aria-label="Mode suppression">
                <p class="magazine-delete-mode-panel__title"><strong>Mode suppression</strong></p>
                <p class="hint">
                    <?php if ($isWishlistIssue): ?>
                        Ce numéro sera retiré de vos envies. Il restera visible dans votre collection si vous l’y aviez déjà référencé.
                    <?php else: ?>
                        Ce numéro sera retiré de vos magazines (collection du foyer). Cette action ne supprime pas le fichier PDF du serveur si un PDF était importé.
                    <?php endif; ?>
                </p>
                <div class="magazine-delete-mode-panel__actions">
                    <?php
                    $possessionFilter = Moncine\MagazineRepository::POSSESSION_ALL;
                    $variant = 'panel';
                    require MONCINE_ROOT . '/templates/_magazine_delete_button.php';
                    ?>
                    <a href="<?= Moncine\View::escape($issuePageUrl) ?>" class="btn btn-secondary btn-sm">Annuler</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Numéro retiré de votre liste.</div>
        <?php endif; ?>
        <?php if ($saved || isset($_GET['added'])): ?>
            <div class="alert alert-success">
                Numéro enregistré.
                <?php if (isset($_GET['pdf'])): ?>
                    Le PDF a bien été importé — bouton « Lire le PDF » ci-dessous.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
        <?php endif; ?>
        <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

        <?php require MONCINE_ROOT . '/templates/_magazine_issue_subjects.php'; ?>

        <section class="magazine-pdf-section">
            <h2>PDF du numéro</h2>
            <?php if ($pdfUrl !== ''): ?>
                <p><a href="<?= Moncine\View::escape($pdfUrl) ?>" class="btn btn-primary" target="_blank" rel="noopener">Lire le PDF</a></p>
            <?php else: ?>
                <p class="hint">Aucun PDF pour l’instant.</p>
            <?php endif; ?>
            <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                <input type="hidden" name="action" value="pdf_only">
                <label for="upload_pdf">Fichier PDF (max <?= Moncine\View::escape(Moncine\UploadLimits::maxPdfBytesLabel()) ?>)</label>
                <input type="file" name="pdf_file" id="upload_pdf" accept="application/pdf,.pdf" required>
                <p class="hint">Limite PHP actuelle : upload <?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?>,
                    post <?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?>.
                    En local, lancez le site avec <code>www/serve.sh</code> si l’envoi échoue.
                    <?php if (Moncine\MagazinePdfCoverExtractor::isAvailable()): ?>
                        Sans couverture, la <strong>page 1 du PDF</strong> peut servir d’image automatiquement.
                    <?php endif; ?>
                </p>
                <button type="submit" class="btn btn-accent"><?= $pdfUrl !== '' ? 'Remplacer le PDF' : 'Importer le PDF' ?></button>
            </form>
            <p class="hint">Stockage : <code><?= Moncine\View::escape(Moncine\MagazineRepository::pdfStorageHint()) ?></code></p>
        </section>

        <div class="magazine-issue-layout">
            <div class="magazine-issue-layout__cover">
                <?php if ($cover !== ''): ?>
                    <img src="<?= $cover ?>" alt="Couverture" class="magazine-cover">
                <?php else: ?>
                    <div class="magazine-cover magazine-cover--empty" aria-hidden="true"></div>
                <?php endif; ?>
            </div>
            <div class="magazine-issue-layout__main">
                <h1><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></h1>
                <p class="lead">
                    Numéro <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                    · <?= Moncine\View::escape($dateLabel) ?>
                    <?php if ((int) ($issue['pages'] ?? 0) > 0): ?>
                        · <?= (int) $issue['pages'] ?> p.
                    <?php endif; ?>
                    <?php require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                    <?php if (($issue['statut'] ?? '') === Moncine\LibraryStatut::COLLECTION && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                        <span class="magazine-tag magazine-tag--none">Non possédé</span>
                    <?php endif; ?>
                </p>

                <?php if (($issue['statut'] ?? '') === Moncine\LibraryStatut::COLLECTION && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                    <div class="magazine-unowned-actions">
                        <p class="hint">Ce numéro est référencé mais vous ne l’avez ni en papier ni en PDF — il n’est pas compté parmi vos numéros possédés.</p>
                        <?php
                        $possessionFilter = Moncine\MagazineRepository::POSSESSION_ALL;
                        require MONCINE_ROOT . '/templates/_magazine_wishlist_button.php';
                        ?>
                    </div>
                <?php elseif ((int) ($issue['in_wishlist'] ?? 0) > 0 && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                    <p class="hint"><span class="magazine-tag magazine-tag--wishlist">En envies</span> — également listé dans vos envies.</p>
                <?php endif; ?>

                <section class="magazine-sommaire">
                    <h2>Sommaire</h2>
                    <?php if (trim((string) ($issue['sommaire'] ?? '')) !== ''): ?>
                        <div class="magazine-sommaire__body"><?= Moncine\View::escape((string) $issue['sommaire']) ?></div>
                    <?php else: ?>
                        <p class="hint">Aucun sommaire renseigné.</p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <details class="import-columns-help">
            <summary>Modifier ce numéro</summary>
            <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                <input type="hidden" name="action" value="save">

                <label for="edit_numero">Numéro</label>
                <input type="text" name="numero" id="edit_numero" required
                       value="<?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>">

                <label for="edit_numero_ordre">Ordre de tri (numérique)</label>
                <input type="number" step="0.1" name="numero_ordre" id="edit_numero_ordre"
                       value="<?= Moncine\View::escape((string) ($issue['numero_ordre'] ?? '')) ?>">

                <label for="edit_date">Date de parution</label>
                <input type="date" name="date_parution" id="edit_date"
                       value="<?= Moncine\View::escape((string) ($issue['date_parution'] ?? '')) ?>">

                <label for="edit_pages">Nombre de pages</label>
                <input type="number" name="pages" id="edit_pages" min="0"
                       value="<?= (int) ($issue['pages'] ?? 0) ?>">
                <?php if (Moncine\MagazinePdfInfo::isAvailable()): ?>
                    <p class="hint">Si la valeur est 0, elle sera remplie automatiquement à l’import du PDF (via pdfinfo).</p>
                <?php endif; ?>

                <fieldset class="magazine-support-fieldset">
                    <legend>Support</legend>
                    <label class="checkbox">
                        <input type="checkbox" name="support_papier" value="1"
                            <?= Moncine\MagazineSupport::hasPaper((string) ($issue['support_physique'] ?? '')) ? ' checked' : '' ?>>
                        J’ai le numéro en <strong>papier</strong>
                    </label>
                    <?php if ((int) ($issue['stored_object_id'] ?? 0) > 0 || Moncine\MagazineSupport::hasPdf((string) ($issue['support_physique'] ?? ''))): ?>
                        <p class="hint">
                            Tag <span class="magazine-tag magazine-tag--pdf">PDF</span>
                            ajouté automatiquement (fichier importé).
                        </p>
                    <?php else: ?>
                        <p class="hint">Le tag <span class="magazine-tag magazine-tag--pdf">PDF</span> s’ajoutera à l’import du fichier.</p>
                    <?php endif; ?>
                </fieldset>
                <label class="checkbox">
                    <input type="checkbox" name="est_hors_serie" id="edit_est_hors_serie" value="1"<?= !empty($issue['est_hors_serie']) ? ' checked' : '' ?>>
                    Hors-série
                </label>

                <label for="edit_sommaire">Sommaire</label>
                <textarea name="sommaire" id="edit_sommaire" rows="8"><?= Moncine\View::escape((string) ($issue['sommaire'] ?? '')) ?></textarea>

                <label for="edit_cover">Nouvelle couverture (JPEG, PNG, WebP)</label>
                <input type="file" name="cover_file" id="edit_cover" accept="image/jpeg,image/png,image/webp">
                <p class="hint">Taille max. <?= Moncine\View::escape(Moncine\UploadLimits::maxPosterBytesLabel()) ?>.</p>

                <label for="edit_pdf">Remplacer le PDF (max <?= Moncine\View::escape(Moncine\UploadLimits::maxPdfBytesLabel()) ?>)</label>
                <input type="file" name="pdf_file" id="edit_pdf" accept="application/pdf,.pdf">
                <p class="hint">Limite serveur PHP : upload <?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?>,
                    post <?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?>.</p>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </form>
        </details>
    <?php endif; ?>
</section>
