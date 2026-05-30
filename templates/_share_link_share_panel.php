<?php
/**
 * Boutons de partage d’un lien visiteur (e-mail, Bluesky).
 *
 * @var string $shareUrl URL absolue
 * @var string $scopeLabel Libellé du contenu partagé
 * @var int $linkId Identifiant du lien (pour envoi serveur)
 * @var string $shareEmailOk
 * @var string $shareEmailError
 */
$scopeLabel = $scopeLabel ?? 'Liste partagée';
$shareUrl = trim($shareUrl ?? '');
$linkId = (int) ($linkId ?? 0);
?>
<?php if ($shareUrl === ''): ?>
    <p class="hint">L’URL complète n’est disponible qu’à la création du lien (ou pendant 24 h dans cette session).</p>
<?php else: ?>
    <div class="share-delivery">
        <p class="share-delivery__url">
            <label class="visually-hidden" for="share_url_copy_<?= $linkId ?>">URL du lien</label>
            <input type="text" id="share_url_copy_<?= $linkId ?>" class="share-delivery__input"
                   value="<?= Moncine\View::escape($shareUrl) ?>" readonly>
            <button type="button" class="btn btn-secondary btn--small share-delivery__copy"
                    data-copy-target="share_url_copy_<?= $linkId ?>">
                Copier
            </button>
        </p>

        <div class="share-delivery__actions">
            <a class="btn btn-secondary btn--small"
               href="<?= Moncine\View::escape(Moncine\ShareLinkShare::mailtoUrl($shareUrl, $scopeLabel)) ?>">
                Ouvrir dans ma messagerie
            </a>
            <a class="btn btn-secondary btn--small" href="<?= Moncine\View::escape(
                Moncine\ShareLinkShare::blueskyIntentUrl($shareUrl, $scopeLabel)
            ) ?>" target="_blank" rel="noopener noreferrer">
                Partager sur Bluesky
            </a>
        </div>

        <?php if ($linkId > 0): ?>
            <form method="post" action="/gerer-partages.php" class="share-delivery__email-form import-form">
                <?= Moncine\View::csrfField() ?>
                <input type="hidden" name="action" value="send_share_email">
                <input type="hidden" name="link_id" value="<?= $linkId ?>">
                <label for="share_email_to_<?= $linkId ?>">Envoyer le lien par e-mail (depuis le serveur)</label>
                <input type="email" name="recipient_email" id="share_email_to_<?= $linkId ?>"
                       required maxlength="254" placeholder="adresse@exemple.fr" autocomplete="email">
                <label for="share_email_msg_<?= $linkId ?>">Message personnel (optionnel)</label>
                <textarea name="personal_message" id="share_email_msg_<?= $linkId ?>" rows="2" maxlength="500"
                          placeholder="Un mot pour la personne qui recevra le lien"></textarea>
                <button type="submit" class="btn btn-primary btn--small">Envoyer l’e-mail</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($shareEmailOk)): ?>
            <p class="alert alert-success"><?= Moncine\View::escape($shareEmailOk) ?></p>
        <?php endif; ?>
        <?php if (!empty($shareEmailError)): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($shareEmailError) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
