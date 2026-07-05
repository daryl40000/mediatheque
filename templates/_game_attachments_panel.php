<?php
/**
 * Fichiers joints (abandonware, patch…).
 *
 * @var int $gameId
 * @var list<array<string, mixed>> $attachments
 */
$gameId = (int) ($gameId ?? 0);
$attachments = $attachments ?? [];
?>
<section class="game-attachments-panel">
    <h2 class="game-attachments-panel__title">Fichiers attachés</h2>
    <p class="hint">
        Abandonware, patch, image disque… (max <?= Moncine\View::escape(Moncine\UploadLimits::maxAttachmentBytesLabel()) ?>).
    </p>

    <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

    <?php if ($attachments !== []): ?>
        <ul class="game-attachments-list" role="list">
            <?php foreach ($attachments as $attachment): ?>
                <?php
                $attachmentId = (int) ($attachment['id'] ?? 0);
                $storedObjectId = (int) ($attachment['stored_object_id'] ?? 0);
                ?>
                <li class="game-attachments-list__item" role="listitem">
                    <a href="/media-object.php?id=<?= $storedObjectId ?>"
                       class="game-attachments-list__link">
                        <?= Moncine\View::escape((string) ($attachment['display_label'] ?? 'Fichier')) ?>
                    </a>
                    <span class="hint game-attachments-list__meta">
                        <?= Moncine\View::escape((string) ($attachment['size_label'] ?? '')) ?>
                    </span>
                    <form method="post" action="/supprimer-fichier-jeu.php" class="inline-form game-attachments-list__delete"
                          onsubmit="return confirm('Supprimer ce fichier ?');">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="game_id" value="<?= $gameId ?>">
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
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="hint">Aucun fichier pour l’instant.</p>
    <?php endif; ?>

    <details class="game-attachments-add">
        <summary class="btn btn-secondary btn-sm game-attachments-add__trigger">Ajouter un fichier</summary>
        <form method="post" action="/enregistrer-fichier-jeu.php" enctype="multipart/form-data" class="game-attachments-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="game_id" value="<?= $gameId ?>">

        <label for="attachment_label">Description (facultatif)</label>
        <input type="text" name="attachment_label" id="attachment_label" maxlength="120"
               placeholder="Ex. Patch FR, ISO abandonware…">

        <label for="attachment_file">Fichier</label>
        <input type="file" name="attachment_file" id="attachment_file" required>
        <p class="hint">
            Limite application : <?= Moncine\View::escape(Moncine\UploadLimits::maxAttachmentBytesLabel()) ?> —
            PHP : upload <?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?>,
            post <?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?>.
        </p>

        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer le fichier</button>
        </form>
    </details>
</section>
