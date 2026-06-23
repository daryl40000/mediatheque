<?php
/** @var array<string, mixed>|null $game */
?>
<section class="share-visitor-page">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p class="hint">Ce titre n’est pas accessible via ce lien de partage.</p>
        <?php if (($rawToken ?? '') !== ''): ?>
            <p><a href="<?= Moncine\View::escape($listUrl ?? '/partage-jeux.php') ?>">← Retour à la liste</a></p>
        <?php endif; ?>
    <?php else:
        $rawToken = (string) ($rawToken ?? '');
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
        $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
        $physicalLabels = $game['physical_support_labels']
            ?? Moncine\GamePhysicalSupport::displayLabels((string) ($game['physical_supports'] ?? ''));
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($listUrl ?? '#') ?>">
                <?= Moncine\View::escape($scopeLabel ?? 'Liste partagée') ?>
            </a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($displayTitle) ?></span>
        </p>

        <p class="hint share-visitor-page__badge">Vue visiteur — lecture seule</p>

        <?php if (($scope ?? '') === Moncine\ShareLinkScope::WISHLIST): ?>
            <p class="hint film-wishlist-badge">Ce jeu figure dans les envies partagées.</p>
        <?php endif; ?>

        <article class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Jaquette de <?= Moncine\View::escape($displayTitle) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1>
                        <?= Moncine\View::escape($displayTitle) ?>
                        <?php if ((int) ($game['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $game['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                </header>

                <dl class="film-facts">
                    <?php if ((string) ($game['platform_short'] ?? '') !== ''): ?>
                        <dt>Plateforme</dt>
                        <dd><?= Moncine\View::escape((string) $game['platform_short']) ?></dd>
                    <?php endif; ?>

                    <?php if (trim((string) ($game['studio'] ?? '')) !== ''): ?>
                        <dt>Studio</dt>
                        <dd><?= Moncine\View::escape((string) $game['studio']) ?></dd>
                    <?php endif; ?>

                    <?php if (trim((string) ($game['editeur'] ?? '')) !== ''): ?>
                        <dt>Éditeur</dt>
                        <dd><?= Moncine\View::escape((string) $game['editeur']) ?></dd>
                    <?php endif; ?>

                    <?php if ($genreList !== []): ?>
                        <dt>Genres</dt>
                        <dd><?= Moncine\View::escape(implode(', ', $genreList)) ?></dd>
                    <?php endif; ?>

                    <?php if (trim((string) ($game['franchise'] ?? '')) !== ''): ?>
                        <dt>Saga</dt>
                        <dd><?= Moncine\View::escape((string) $game['franchise']) ?></dd>
                    <?php endif; ?>

                    <?php if ($physicalLabels !== [] || !empty($game['is_digital'])): ?>
                        <dt>Éditions</dt>
                        <dd>
                            <?php
                            $iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
                            $supplementalText = Moncine\GameEditionIcons::supplementalText($game);
                            require MONCINE_ROOT . '/templates/_game_edition_icons.php';
                            ?>
                        </dd>
                    <?php endif; ?>
                </dl>

                <?php if (trim((string) ($game['synopsis'] ?? '')) !== ''): ?>
                    <section class="film-synopsis">
                        <h2>Description</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $game['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>
</section>
