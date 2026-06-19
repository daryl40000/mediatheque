<?php
/**
 * Fiche catalogue — jeu vidéo.
 *
 * @var array<string, mixed>|null $game
 * @var array<string, mixed>|null $library
 * @var int|null $libraryBibId
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 * @var array<string, mixed>|null $baseGame
 * @var list<array<string, mixed>> $catalogExtensions
 */
$navLabels = Moncine\MediaDomain::navLabels(Moncine\MediaDomain::JEU);
?>
<section class="oeuvre-catalog-page oeuvre-catalog-page--game">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p>Cette fiche n’existe pas ou a été supprimée du catalogue.</p>
        <a href="<?= Moncine\View::escape($catalogueBackUrl) ?>" class="btn btn-primary">Retour au catalogue</a>
    <?php else:
        $oeuvreId = (int) ($game['oeuvre_id'] ?? $oeuvreId ?? 0);
        $libraryEntry = $library;
        $inLibrary = $libraryEntry !== null && ($libraryBibId ?? 0) > 0;
        $libraryStatut = $inLibrary ? (string) ($libraryEntry['statut'] ?? '') : '';
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
        $physicalLabels = $game['physical_support_labels']
            ?? Moncine\GamePhysicalSupport::displayLabels((string) ($game['physical_supports'] ?? ''));
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($catalogueBackUrl) ?>">Catalogue</a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?></span>
        </p>

        <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

        <p class="hint oeuvre-catalog-page__badge">
            Fiche catalogue jeu vidéo (ID <?= $oeuvreId ?>)
            <?php if ($libraryCount > 0): ?>
                — <?= $libraryCount ?> entrée<?= $libraryCount > 1 ? 's' : '' ?> bibliothèque
            <?php endif; ?>
        </p>

        <?php if (isset($catalogListContext, $oeuvreNav)): ?>
            <div id="catalog-oeuvre-nav" class="catalog-oeuvre-nav-anchor">
                <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
            </div>
        <?php endif; ?>

        <article class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Jaquette de <?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?></span>
                        <?php if (!empty($game['is_extension'])): ?>
                            <span class="magazine-tag">Extension</span>
                        <?php endif; ?>
                        <?php if (!empty($game['is_remake'])): ?>
                            <span class="magazine-tag">Remake</span>
                        <?php endif; ?>
                        <?php if ((int) ($game['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $game['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                    <p class="lead">
                        <?php
                        $meta = [];
                        if ((string) ($game['platform_short'] ?? '') !== '') {
                            $meta[] = (string) $game['platform_short'];
                        }
                        if (!empty($game['has_digital_edition'])) {
                            $meta[] = 'Démat';
                        } elseif ($physicalLabels !== []) {
                            $meta[] = 'Physique';
                        }
                        echo Moncine\View::escape($meta !== [] ? implode(' · ', $meta) : 'Jeu vidéo');
                        ?>
                    </p>
                </header>

                <dl class="film-facts">
                    <?php if ((string) ($game['studio'] ?? '') !== ''): ?>
                        <dt>Studio</dt>
                        <dd><?= Moncine\View::escape((string) $game['studio']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) ($game['editeur'] ?? '') !== ''): ?>
                        <dt>Éditeur</dt>
                        <dd><?= Moncine\View::escape((string) $game['editeur']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) ($game['platform_label'] ?? '') !== ''): ?>
                        <dt>Plateforme</dt>
                        <dd><?= Moncine\View::escape((string) $game['platform_label']) ?></dd>
                    <?php endif; ?>

                    <?php if ($genreList !== []): ?>
                        <dt>Genres</dt>
                        <dd class="game-genre-tags">
                            <?php foreach ($genreList as $genreTag): ?>
                                <span class="magazine-tag magazine-tag--game-genre"><?= Moncine\View::escape((string) $genreTag) ?></span>
                            <?php endforeach; ?>
                        </dd>
                    <?php endif; ?>

                    <?php require MONCINE_ROOT . '/templates/_game_igdb_metadata_display.php'; ?>

                    <?php if ((string) ($game['edition_summary'] ?? '') !== ''): ?>
                        <dt>Éditions catalogue</dt>
                        <dd><?= Moncine\View::escape((string) $game['edition_summary']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php
                $gameRelatedSections = Moncine\GameRelatedSections::build(
                    $game,
                    $baseGame ?? null,
                    $originalGame ?? null,
                    $catalogExtensions ?? [],
                    $catalogRemakes ?? [],
                    static fn (array $row): string => Moncine\View::oeuvreJeuUrl(
                        (int) ($row['oeuvre_id'] ?? 0),
                        $catalogSearch ?? '',
                        $catalogSort ?? 'titre',
                        $catalogDir ?? 'asc',
                        (int) ($catalogPage ?? 1),
                    ),
                );
                require MONCINE_ROOT . '/templates/_game_related_posters.php';
                ?>

                <?php if (!empty($game['synopsis'])): ?>
                    <h2>Description</h2>
                    <p class="film-synopsis"><?= Moncine\View::escape((string) $game['synopsis']) ?></p>
                <?php endif; ?>

                <?php if (!empty($saved)): ?>
                    <p class="alert alert-success">Modifications enregistrées.</p>
                <?php endif; ?>
                <?php if (!empty($posterUploaded)): ?>
                    <p class="alert alert-success">Jaquette enregistrée.</p>
                <?php endif; ?>

                <?php
                require MONCINE_ROOT . '/templates/_oeuvre_jeu_edit_form.php';
                ?>

                <?php
                $enrichTarget = 'oeuvre';
                $entityId = $oeuvreId;
                $hasIgdbCredentials = Moncine\IgdbConfig::hasCredentials() && Moncine\GameRepository::hasIgdbColumns();
                $currentIgdbId = (int) ($game['igdb_id'] ?? 0);
                require MONCINE_ROOT . '/templates/_enrich_game_panel.php';
                ?>

                <?php
                $posterUploadError = $posterUploadError ?? '';
                $posterUploadOpen = $posterUploadOpen ?? false;
                require MONCINE_ROOT . '/templates/_oeuvre_poster_upload_form.php';
                ?>

                <?php
                $mediaDomain = Moncine\MediaDomain::JEU;
                $collectionLabel = $navLabels['collection'];
                $wishlistLabel = $navLabels['wishlist'];
                $openLibraryLabel = 'Ouvrir ma fiche jeu';
                require MONCINE_ROOT . '/templates/_oeuvre_catalog_library_section.php';
                ?>

                <?php if (isset($catalogListContext, $oeuvreNav)): ?>
                    <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
                <?php endif; ?>
            </div>
        </article>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($catalogueBackUrl) ?>">← Retour au catalogue</a>
        </p>
    <?php endif; ?>
</section>
