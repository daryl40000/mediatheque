<?php
/** @var array<string, mixed>|null $film */
?>
<section class="share-visitor-page">
    <?php if ($film === null): ?>
        <h1>Film introuvable</h1>
        <p class="hint">Ce titre n’est pas accessible via ce lien de partage.</p>
        <?php if (($rawToken ?? '') !== ''): ?>
            <p><a href="<?= Moncine\View::escape($listUrl ?? '/partage.php') ?>">← Retour à la liste</a></p>
        <?php endif; ?>
    <?php else:
        $filmId = (int) ($film['id'] ?? 0);
        $rawToken = (string) ($rawToken ?? '');
        $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($listUrl ?? '#') ?>">
                <?= Moncine\View::escape($scopeLabel ?? 'Liste partagée') ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></span>
        </p>

        <p class="hint share-visitor-page__badge">Vue visiteur — lecture seule</p>

        <article class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Affiche de <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1>
                        <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>
                        <?php if ((int) ($film['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $film['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                </header>

                <dl class="film-facts">
                    <dt>Réalisateur</dt>
                    <dd><?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?></dd>

                    <?php if (($supportLabel ?? '') !== ''): ?>
                        <dt>Support</dt>
                        <dd><?= Moncine\View::escape($supportLabel) ?></dd>
                    <?php endif; ?>

                    <?php if (trim((string) ($film['saga'] ?? '')) !== ''): ?>
                        <dt>Saga</dt>
                        <dd><?= Moncine\View::escape((string) $film['saga']) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($catalogEan)): ?>
                        <dt>EAN catalogue (<?= Moncine\View::escape($supportLabel ?? 'support') ?>)</dt>
                        <dd><code><?= Moncine\View::escape(Moncine\View::formatEan((string) $catalogEan)) ?></code></dd>
                    <?php endif; ?>

                    <?php if (($scope ?? '') === Moncine\ShareLinkScope::WISHLIST): ?>
                        <dt>Versions recherchées</dt>
                        <dd>
                            <?php
                            $emptyHint = 'Aucune version précisée pour cette envie.';
                            require MONCINE_ROOT . '/templates/_wishlist_targets_readonly.php';
                            ?>
                        </dd>
                    <?php endif; ?>

                    <?php if (trim((string) ($film['ean'] ?? '')) !== ''): ?>
                        <dt>Code-barres exemplaire</dt>
                        <dd><code><?= Moncine\View::escape(Moncine\View::formatEan((string) $film['ean'])) ?></code></dd>
                    <?php endif; ?>
                </dl>

                <?php if (trim((string) ($film['synopsis'] ?? '')) !== ''): ?>
                    <section class="film-synopsis">
                        <h2>Synopsis</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $film['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>
            </div>
        </article>

        <p><a href="<?= Moncine\View::escape($listUrl ?? '#') ?>">← Retour à la liste partagée</a></p>
    <?php endif; ?>
</section>
