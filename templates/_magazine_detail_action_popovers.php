<?php
/**
 * Boutons d’action rapide fiche magazine : modifier, PDF.
 *
 * @var array<string, mixed> $issue
 * @var int $bibId
 * @var int $seriesId
 * @var string $pdfUrl
 * @var string $popoverOpen edit|pdf
 * @var string $error
 * @var list<array<string, mixed>> $supplements
 */
$bibId = (int) ($bibId ?? 0);
$seriesId = (int) ($seriesId ?? 0);
$issue = $issue ?? [];
$pdfUrl = trim((string) ($pdfUrl ?? ''));
$hasPdf = $pdfUrl !== '';
$popoverOpen = (string) ($popoverOpen ?? '');
$error = (string) ($error ?? '');
$supplements = $supplements ?? [];
$supplementsAvailable = Moncine\MagazineIssueSupplementRepository::isAvailable();
?>
<div class="game-detail-sidebar__actions"
     data-detail-actions
     <?= $popoverOpen !== '' ? ' data-popover-open="' . Moncine\View::escape($popoverOpen) . '"' : '' ?>>
    <div class="game-action-popover-anchor" data-detail-action-anchor="edit">
                <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="edit"
                title="Modifier ce numéro" aria-label="Modifier ce numéro"
                aria-expanded="<?= $popoverOpen === 'edit' ? 'true' : 'false' ?>"
                aria-controls="magazine-popover-edit">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="magazine-popover-edit" data-detail-popover="edit" role="dialog"
             aria-label="Modifier ce numéro" hidden>
            <div class="game-action-popover__panel game-action-popover__panel--wide">
                <p class="game-action-popover__title">Modifier ce numéro</p>
                <?php if ($error !== '' && $popoverOpen === 'edit'): ?>
                    <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
                <?php endif; ?>
                <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form film-edit-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                    <input type="hidden" name="series_id" value="<?= $seriesId ?>">
                    <input type="hidden" name="action" value="save">

                    <label for="popover_edit_numero">Numéro</label>
                    <input type="text" name="numero" id="popover_edit_numero" required
                           value="<?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?>">

                    <label for="popover_edit_date">Date de parution</label>
                    <input type="date" name="date_parution" id="popover_edit_date"
                           value="<?= Moncine\View::escape((string) ($issue['date_parution'] ?? '')) ?>">

                    <label for="popover_edit_pages">Nombre de pages</label>
                    <input type="number" name="pages" id="popover_edit_pages" min="0"
                           value="<?= (int) ($issue['pages'] ?? 0) ?>">

                    <fieldset class="magazine-support-fieldset">
                        <legend>Support</legend>
                        <label class="checkbox">
                            <input type="checkbox" name="support_papier" value="1"
                                <?= Moncine\MagazineSupport::hasPaper((string) ($issue['support_physique'] ?? '')) ? ' checked' : '' ?>>
                            J’ai le numéro en <strong>papier</strong>
                        </label>
                    </fieldset>

                    <!-- Case HS : sans elle, l’enregistrement remettait toujours hors-série à « non ». -->
                    <label class="checkbox">
                        <input type="checkbox" name="est_hors_serie" id="popover_edit_est_hors_serie" value="1"
                            <?= !empty($issue['est_hors_serie']) ? ' checked' : '' ?>>
                        Hors-série / numéro spécial
                    </label>

                    <label for="popover_edit_sommaire">Sommaire</label>
                    <textarea name="sommaire" id="popover_edit_sommaire" rows="5"><?= Moncine\View::escape((string) ($issue['sommaire'] ?? '')) ?></textarea>

                    <label for="popover_edit_cover">Nouvelle couverture</label>
                    <input type="file" name="cover_file" id="popover_edit_cover" accept="image/jpeg,image/png,image/webp">

                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($hasPdf): ?>
        <a href="<?= Moncine\View::escape($pdfUrl) ?>"
           class="btn btn-accent btn-sm magazine-issue-card__pdf"
           target="_blank"
           rel="noopener"
           title="Ouvrir le PDF"
           aria-label="Ouvrir le PDF du numéro">PDF</a>
    <?php endif; ?>

    <div class="game-action-popover-anchor" data-detail-action-anchor="pdf">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="pdf"
                title="<?= $hasPdf ? 'Gérer le PDF' : 'Importer le PDF' ?>"
                aria-label="<?= $hasPdf ? 'Gérer le PDF du numéro' : 'Importer ou lire le PDF' ?>"
                aria-expanded="<?= $popoverOpen === 'pdf' ? 'true' : 'false' ?>"
                aria-controls="magazine-popover-pdf">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2 5 5h-5V4zM8 13h8v2H8v-2zm0 4h8v2H8v-2z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="magazine-popover-pdf" data-detail-popover="pdf" role="dialog"
             aria-label="PDF du numéro" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title">PDF du numéro</p>
                <?php if ($error !== '' && $popoverOpen === 'pdf'): ?>
                    <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
                <?php endif; ?>

                <?php if ($hasPdf): ?>
                    <p class="magazine-pdf-popover__actions">
                        <a href="<?= Moncine\View::escape($pdfUrl) ?>"
                           class="btn btn-primary btn-sm"
                           target="_blank" rel="noopener">
                            Lire le PDF
                        </a>
                    </p>
                    <p class="hint">Remplacez le fichier ou retirez-le de votre bibliothèque (le numéro reste en collection).</p>
                    <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form magazine-pdf-popover__replace">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                        <input type="hidden" name="action" value="pdf_only">
                        <label for="popover_replace_pdf">Remplacer par un autre PDF</label>
                        <input type="file" name="pdf_file" id="popover_replace_pdf" accept="application/pdf,.pdf" required>
                        <button type="submit" class="btn btn-secondary btn-sm">Remplacer le PDF</button>
                    </form>
                    <form method="post" action="/traiter-numero-magazine.php" class="magazine-pdf-popover__remove"
                          onsubmit="return confirm('Retirer le PDF de ce numéro ? Le fichier sera supprimé du serveur.');">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                        <input type="hidden" name="action" value="remove_pdf">
                        <button type="submit" class="btn btn-danger-text btn-sm">Retirer le PDF</button>
                    </form>
                <?php else: ?>
                    <p class="hint">Aucun PDF pour l’instant. Importez un fichier ci-dessous.</p>
                    <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                        <input type="hidden" name="action" value="pdf_only">
                        <label for="popover_upload_pdf">Fichier PDF</label>
                        <input type="file" name="pdf_file" id="popover_upload_pdf" accept="application/pdf,.pdf" required>
                        <button type="submit" class="btn btn-primary">Importer le PDF</button>
                    </form>
                <?php endif; ?>

                <?php if ($supplementsAvailable): ?>
                    <hr class="magazine-pdf-popover__sep">
                    <p class="game-action-popover__title">Suppléments / livrets</p>
                    <p class="hint">PDF bonus (posters, livret, CD…). La 1re page sert de vignette ; le texte est indexé pour la recherche.</p>

                    <?php if ($supplements !== []): ?>
                        <ul class="magazine-supplement-manage-list" role="list">
                            <?php foreach ($supplements as $supplement):
                                $suppId = (int) ($supplement['id'] ?? 0);
                                $suppLabel = (string) ($supplement['display_label'] ?? 'Supplément');
                                $suppPdfUrl = trim((string) ($supplement['pdf_url'] ?? ''));
                                ?>
                                <li class="magazine-supplement-manage-list__item" role="listitem">
                                    <span class="magazine-supplement-manage-list__label">
                                        <?= Moncine\View::escape($suppLabel) ?>
                                        <?php if ((int) ($supplement['pages'] ?? 0) > 0): ?>
                                            <span class="hint">(<?= (int) $supplement['pages'] ?> p.)</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="magazine-supplement-manage-list__actions">
                                        <?php if ($suppId > 0): ?>
                                            <a href="<?= Moncine\View::escape(Moncine\View::magazineSupplementUrl($bibId, $suppId)) ?>"
                                               class="btn btn-ghost btn-sm">Fiche</a>
                                        <?php endif; ?>
                                        <?php if ($suppPdfUrl !== ''): ?>
                                            <a href="<?= Moncine\View::escape($suppPdfUrl) ?>"
                                               class="btn btn-ghost btn-sm"
                                               target="_blank" rel="noopener">Lire</a>
                                        <?php endif; ?>
                                        <form method="post" action="/traiter-numero-magazine.php" class="inline-form"
                                              onsubmit="return confirm('Retirer ce supplément ? Le fichier sera supprimé.');">
                                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                            <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                                            <input type="hidden" name="action" value="remove_supplement">
                                            <input type="hidden" name="supplement_id" value="<?= $suppId ?>">
                                            <button type="submit" class="btn btn-danger-text btn-sm">Retirer</button>
                                        </form>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="/traiter-numero-magazine.php" enctype="multipart/form-data" class="import-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="bib_id" value="<?= $bibId ?>">
                        <input type="hidden" name="action" value="add_supplement">
                        <label for="popover_supplement_label">Libellé (optionnel)</label>
                        <input type="text" name="supplement_label" id="popover_supplement_label"
                               maxlength="120" placeholder="Ex. Livret posters, CD-ROM…">
                        <label for="popover_supplement_pdf">PDF du supplément</label>
                        <input type="file" name="supplement_pdf_file[]" id="popover_supplement_pdf"
                               accept="application/pdf,.pdf" multiple required>
                        <p class="hint">Vous pouvez sélectionner plusieurs fichiers d’un coup.</p>
                        <button type="submit" class="btn btn-secondary btn-sm">Ajouter le(s) supplément(s)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
