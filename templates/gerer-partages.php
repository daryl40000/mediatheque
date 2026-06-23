<?php
/** @var list<array<string, mixed>> $links */
?>
<section>
    <h1>Liens de partage</h1>
    <p class="hint">
        Créez un lien lecture seule pour montrer vos films ou vos jeux (collection du foyer) ou vos envies à quelqu’un
        sans compte Moncine. Vous pouvez révoquer un lien à tout moment.
    </p>

    <?php if (!empty($flash)): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($flash) ?></p>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($flashError) ?></p>
    <?php endif; ?>

    <?php if (!empty($newShareAbsoluteUrl)): ?>
        <section class="share-manage__new-link alert alert-info">
            <h2>Nouveau lien — partager</h2>
            <?php
            $shareUrl = $newShareAbsoluteUrl;
            $scopeLabel = $newShareScopeLabel ?? 'Liste partagée';
            $linkId = (int) ($newShareLinkId ?? 0);
            require MONCINE_ROOT . '/templates/_share_link_share_panel.php';
            ?>
        </section>
    <?php endif; ?>

    <section class="share-manage__create">
        <h2>Nouveau lien</h2>
        <form method="post" action="/gerer-partages.php" class="import-form">
            <?= Moncine\View::csrfField() ?>
            <input type="hidden" name="action" value="create">

            <?php
            $defaultDomain = Moncine\MediaDomain::normalize($defaultDomain ?? Moncine\MediaDomain::FILM);
            $defaultScope = Moncine\ShareLinkScope::normalize($defaultScope ?? Moncine\ShareLinkScope::COLLECTION);
            ?>

            <label for="share_media_domain">Type de contenu</label>
            <select name="media_domain" id="share_media_domain" required>
                <option value="<?= Moncine\MediaDomain::FILM ?>"
                    <?= $defaultDomain === Moncine\MediaDomain::FILM ? ' selected' : '' ?>>
                    Films
                </option>
                <option value="<?= Moncine\MediaDomain::JEU ?>"
                    <?= $defaultDomain === Moncine\MediaDomain::JEU ? ' selected' : '' ?>>
                    Jeux vidéo
                </option>
            </select>

            <label for="share_scope">Liste à partager</label>
            <select name="scope" id="share_scope" required>
                <option value="<?= Moncine\ShareLinkScope::COLLECTION ?>"
                    <?= $defaultScope === Moncine\ShareLinkScope::COLLECTION ? ' selected' : '' ?>>
                    Collection du foyer
                </option>
                <option value="<?= Moncine\ShareLinkScope::WISHLIST ?>"
                    <?= $defaultScope === Moncine\ShareLinkScope::WISHLIST ? ' selected' : '' ?>>
                    Mes envies (liste personnelle)
                </option>
            </select>

            <label for="share_label">Libellé interne (optionnel)</label>
            <input type="text" name="label" id="share_label" maxlength="120"
                   placeholder="Ex. Lien pour la famille">

            <button type="submit" class="btn btn-primary">Créer un lien</button>
        </form>
        <p class="hint">
            Chaque lien expire au bout de 90 jours. Maximum
            <?= (int) Moncine\ShareLinkService::MAX_ACTIVE_LINKS_PER_USER ?> liens actifs par compte.
            L’URL complète est mémorisée 24 h dans votre session pour l’e-mail et Bluesky.
        </p>
    </section>

    <section class="share-manage__list">
        <h2>Liens actifs</h2>
        <?php if ($links === []): ?>
            <p class="hint">Aucun lien actif pour le moment.</p>
        <?php else: ?>
            <ul class="share-link-list">
                <?php foreach ($links as $link): ?>
                    <?php
                    $linkId = (int) ($link['id'] ?? 0);
                    $scope = Moncine\ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
                    $linkDomain = Moncine\ShareLinkRepository::mediaDomainFromRow($link);
                    $scopeLabel = Moncine\ShareLinkScope::label($scope, $linkDomain);
                    $expires = (string) ($link['expires_at'] ?? '');
                    $shareUrl = $shareUrlByLinkId[$linkId] ?? '';
                    ?>
                    <li class="share-link-list__item">
                        <strong><?= Moncine\View::escape($scopeLabel) ?></strong>
                        <?php if (trim((string) ($link['label'] ?? '')) !== ''): ?>
                            — <?= Moncine\View::escape((string) $link['label']) ?>
                        <?php endif; ?>
                        <span class="hint">
                            Créé le <?= Moncine\View::escape((string) ($link['created_at'] ?? '')) ?>
                            <?php if ($expires !== ''): ?>
                                — expire le <?= Moncine\View::escape($expires) ?>
                            <?php endif; ?>
                            — <?= (int) ($link['access_count'] ?? 0) ?> consultation<?= (int) ($link['access_count'] ?? 0) > 1 ? 's' : '' ?>
                        </span>

                        <?php if ($shareUrl !== ''): ?>
                            <div class="share-link-list__delivery">
                                <?php
                                require MONCINE_ROOT . '/templates/_share_link_share_panel.php';
                                ?>
                            </div>
                        <?php else: ?>
                            <p class="hint share-link-list__no-url">
                                Pour partager ce lien par e-mail ou Bluesky, recréez un lien du même type
                                (l’URL n’est affichée qu’à la création).
                            </p>
                        <?php endif; ?>

                        <form method="post" action="/gerer-partages.php" class="inline-form">
                            <?= Moncine\View::csrfField() ?>
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="link_id" value="<?= $linkId ?>">
                            <button type="submit" class="btn btn-secondary btn--small">Révoquer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <p><a href="/parametres.php">← Retour aux paramètres</a></p>
</section>
