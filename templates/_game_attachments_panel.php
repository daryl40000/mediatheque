<?php
/**
 * Fichiers joints à la fiche jeu catalogue : PDF (manuel, soluce…), patch, archive…
 *
 * Lecture : tout le monde. Ajout / suppression : admin catalogue uniquement.
 *
 * @var int $oeuvreId
 * @var list<array<string, mixed>> $attachments
 * @var bool $canManageAttachments
 */
$oeuvreId = (int) ($oeuvreId ?? 0);
$attachments = $attachments ?? [];
$canManageAttachments = !empty($canManageAttachments);
$maxAttachmentLabel = Moncine\UploadLimits::maxAttachmentBytesLabel();
?>
<section class="game-attachments-panel" id="game-attachments">
    <?php
    unset($info, $infoHtml, $infoAria, $title, $tag, $class);
    $title = 'Manuels, soluces et fichiers';
    $tag = 'h2';
    $class = 'game-attachments-panel__title';
    if ($canManageAttachments) {
        $info = 'Ajoutez un ou plusieurs PDF (manuel, soluce, guide…) ou d’autres fichiers '
            . '(patch, image disque…). Ces fichiers sont partagés pour tout le monde. '
            . 'Max ' . $maxAttachmentLabel . ' par fichier.';
    } else {
        $info = 'Manuels, soluces et autres fichiers partagés sur cette fiche catalogue. '
            . 'Seuls les administrateurs peuvent en ajouter.';
    }
    $infoAria = 'Aide sur les fichiers joints';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($info, $infoAria, $title, $tag, $class);
    ?>

    <?php if ($canManageAttachments): ?>
        <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>
    <?php endif; ?>

    <?php if ($attachments !== []): ?>
        <ul class="game-attachments-list" role="list">
            <?php foreach ($attachments as $attachment): ?>
                <?php
                $attachmentId = (int) ($attachment['id'] ?? 0);
                $storedObjectId = (int) ($attachment['stored_object_id'] ?? 0);
                $isPdf = !empty($attachment['is_pdf']);
                $linkClass = 'game-attachments-list__link'
                    . ($isPdf ? ' game-attachments-list__link--pdf' : '');
                ?>
                <li class="game-attachments-list__item" role="listitem">
                    <a href="/media-object.php?id=<?= $storedObjectId ?>"
                       class="<?= Moncine\View::escape($linkClass) ?>"
                       <?= $isPdf ? 'target="_blank" rel="noopener"' : '' ?>>
                        <?php if ($isPdf): ?>
                            <span class="magazine-tag magazine-tag--pdf game-attachments-list__badge">PDF</span>
                        <?php endif; ?>
                        <?= Moncine\View::escape((string) ($attachment['display_label'] ?? 'Fichier')) ?>
                    </a>
                    <span class="hint game-attachments-list__meta">
                        <?= Moncine\View::escape((string) ($attachment['size_label'] ?? '')) ?>
                    </span>
                    <?php if ($canManageAttachments): ?>
                        <form method="post" action="/supprimer-fichier-jeu.php" class="inline-form game-attachments-list__delete"
                              onsubmit="return confirm('Supprimer ce fichier ?');">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">
                            <input type="hidden" name="attachment_id" value="<?= $attachmentId ?>">
                            <button type="submit"
                                    class="btn btn-icon btn-danger-text btn-sm"
                                    title="Supprimer le fichier"
                                    aria-label="Supprimer le fichier">
                                <svg class="icon-trash" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="hint">Aucun fichier pour l’instant.</p>
    <?php endif; ?>

    <?php if ($canManageAttachments): ?>
        <details class="game-attachments-add">
            <summary class="btn btn-secondary btn-sm game-attachments-add__trigger">Ajouter des fichiers</summary>
            <form method="post" action="/enregistrer-fichier-jeu.php" enctype="multipart/form-data" class="game-attachments-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">

                <?php
                unset($info, $infoHtml, $infoAria, $for, $label);
                $for = 'attachment_kind';
                $label = 'Type (facultatif)';
                $info = 'Sert de libellé si vous ne renseignez pas la description ci-dessous.';
                $infoAria = 'Aide sur le type de fichier';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria, $for, $label);
                ?>
                <select name="attachment_kind" id="attachment_kind">
                    <option value="">— Choisir —</option>
                    <option value="Manuel">Manuel</option>
                    <option value="Soluce">Soluce</option>
                    <option value="Guide">Guide</option>
                    <option value="Carte">Carte</option>
                    <option value="Patch">Patch</option>
                    <option value="Autre">Autre</option>
                </select>

                <label for="attachment_label">Description (facultatif)</label>
                <input type="text" name="attachment_label" id="attachment_label" maxlength="120"
                       placeholder="Ex. Manuel FR, Soluce complète…">

                <?php
                unset($info, $infoHtml, $infoAria, $for, $label);
                $for = 'attachment_file';
                $label = 'Fichier(s)';
                $info = 'Vous pouvez sélectionner plusieurs PDF (ou archives) en une fois. '
                    . 'Limite : ' . $maxAttachmentLabel . ' / fichier — PHP : upload '
                    . Moncine\UploadLimits::uploadMaxFilesizeLabel()
                    . ', post ' . Moncine\UploadLimits::postMaxSizeLabel() . '.';
                $infoAria = 'Aide sur l’envoi de fichiers';
                require MONCINE_ROOT . '/templates/_form_label_info.php';
                unset($info, $infoAria, $for, $label);
                ?>
                <input type="file" name="attachment_file[]" id="attachment_file" required multiple
                       accept=".pdf,application/pdf,.zip,.7z,.rar,.iso,.img,.bin,.cue,.nrg,.gz,.tar">

                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
            </form>
        </details>
    <?php elseif (Moncine\CatalogAdmin::canAccess() && $oeuvreId > 0): ?>
        <p class="hint">
            Pour ajouter ou retirer des fichiers,
            ouvrez la <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($oeuvreId)) ?>">fiche catalogue</a>.
        </p>
    <?php endif; ?>
</section>
