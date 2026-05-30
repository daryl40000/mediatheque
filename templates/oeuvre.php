<?php
/**
 * Fiche catalogue (œuvre partagée).
 *
 * @var array<string, mixed>|null $oeuvre
 * @var array<string, mixed>|null $library
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 */
?>
<section class="oeuvre-catalog-page">
    <?php if ($oeuvre === null): ?>
        <h1>Œuvre introuvable</h1>
        <p>Cette fiche n’existe pas ou a été supprimée du catalogue.</p>
        <a href="<?= Moncine\View::escape($catalogueBackUrl) ?>" class="btn btn-primary">Retour au catalogue</a>
    <?php else:
        $oeuvreId = (int) ($oeuvre['id'] ?? 0);
        $libraryEntry = $library;
        $inLibrary = $libraryEntry !== null;
        $libraryStatut = $inLibrary ? (string) ($libraryEntry['statut'] ?? '') : '';
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($catalogueBackUrl) ?>">Catalogue</a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?></span>
        </p>

        <p class="hint oeuvre-catalog-page__badge">
            Fiche catalogue partagée (ID <?= $oeuvreId ?>)
            <?php if ($libraryCount > 0): ?>
                — <?= $libraryCount ?> entrée<?= $libraryCount > 1 ? 's' : '' ?> bibliothèque
            <?php endif; ?>
        </p>

        <?php if (isset($catalogListContext, $oeuvreNav)): ?>
            <div id="catalog-oeuvre-nav" class="catalog-oeuvre-nav-anchor">
                <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
            </div>
        <?php endif; ?>

        <?php $posterSrc = Moncine\View::posterSrc($oeuvre['poster_url'] ?? null); ?>
        <article class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Affiche de <?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1>
                        <?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?>
                        <?php if ((int) ($oeuvre['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $oeuvre['annee'] ?>)</span>
                        <?php endif; ?>
                    </h1>
                    <?php if (trim((string) ($oeuvre['titre_original'] ?? '')) !== ''): ?>
                        <p class="film-original-title" lang="und">
                            <?= Moncine\View::escape((string) $oeuvre['titre_original']) ?>
                        </p>
                    <?php endif; ?>
                </header>

                <dl class="film-facts">
                    <dt>Réalisateur</dt>
                    <dd><?php
                        $name = (string) ($oeuvre['realisateur'] ?? '');
                        require MONCINE_ROOT . '/templates/_personne_link.php';
                        ?></dd>

                    <?php
                    $acteurs = Moncine\FilmManualEdit::acteursList($oeuvre);
                    if ($acteurs !== []):
                        ?>
                        <dt>Acteurs principaux</dt>
                        <dd class="personnes-list">
                            <?php foreach ($acteurs as $i => $acteurName): ?>
                                <?php if ($i > 0): ?><span class="personnes-sep">, </span><?php endif; ?>
                                <?php $name = $acteurName; require MONCINE_ROOT . '/templates/_personne_link.php'; ?>
                            <?php endforeach; ?>
                        </dd>
                    <?php endif; ?>

                    <dt>Catégorie</dt>
                    <dd><?= Moncine\View::escape(Moncine\View::contentKindLabel($oeuvre)) ?></dd>

                    <dt>Année</dt>
                    <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatAnnee((int) ($oeuvre['annee'] ?? 0))) ?></dd>

                    <dt>Nationalité</dt>
                    <dd><?= Moncine\View::escape(
                        Moncine\FilmRepository::formatNationalite((string) ($oeuvre['nationalite'] ?? ''))
                    ) ?></dd>

                    <dt>Durée</dt>
                    <dd><?= Moncine\View::escape(Moncine\FilmRepository::formatDuree((int) ($oeuvre['duree_min'] ?? 0))) ?></dd>

                    <dt>Style</dt>
                    <dd><?= ($oeuvre['styles'] ?? '') !== '' ? Moncine\View::escape((string) $oeuvre['styles']) : '—' ?></dd>

                    <?php if ((int) ($oeuvre['tmdb_id'] ?? 0) > 0): ?>
                        <dt>Identifiant TMDB</dt>
                        <dd>
                            <?php
                            $tmdbUrl = Moncine\TmdbMediaType::publicUrl(
                                (int) $oeuvre['tmdb_id'],
                                (string) ($oeuvre['tmdb_media_type'] ?? '')
                            );
                            ?>
                            <a href="<?= Moncine\View::escape($tmdbUrl) ?>"
                               target="_blank" rel="noopener">
                                <?= Moncine\View::escape(Moncine\TmdbMediaType::label(
                                    (string) ($oeuvre['tmdb_media_type'] ?? ''),
                                    (string) ($oeuvre['tmdb_tv_kind'] ?? '')
                                )) ?>
                                #<?= (int) $oeuvre['tmdb_id'] ?>
                            </a>
                        </dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($oeuvre['synopsis'])): ?>
                    <h2>Synopsis</h2>
                    <p class="film-synopsis"><?= Moncine\View::escape((string) $oeuvre['synopsis']) ?></p>
                <?php endif; ?>

                <?php if (isset($_GET['added']) && (string) $_GET['added'] === '1'): ?>
                    <p class="alert alert-success">Œuvre ajoutée au catalogue.</p>
                <?php endif; ?>
                <?php if (!empty($saved)): ?>
                    <p class="alert alert-success">Modifications enregistrées.</p>
                <?php endif; ?>
                <?php if (!empty($posterUploaded)): ?>
                    <p class="alert alert-success">Affiche enregistrée.</p>
                <?php endif; ?>

                <?php
                $editOpen = $editOpen ?? false;
                $saveError = $saveError ?? '';
                $catalogSearch = $catalogSearch ?? '';
                $catalogSort = $catalogSort ?? 'titre';
                $catalogDir = $catalogDir ?? 'asc';
                $catalogPage = (int) ($catalogPage ?? 1);
                require MONCINE_ROOT . '/templates/_oeuvre_edit_form.php';
                ?>

                <?php
                $posterUploadError = $posterUploadError ?? '';
                $posterUploadOpen = $posterUploadOpen ?? false;
                require MONCINE_ROOT . '/templates/_oeuvre_poster_upload_form.php';
                ?>

                <?php
                $enrichTarget = 'oeuvre';
                $entityId = $oeuvreId;
                require MONCINE_ROOT . '/templates/_enrich_entity_panel.php';
                ?>

                <?php
                require MONCINE_ROOT . '/templates/_oeuvre_eans.php';
                ?>

                <section class="oeuvre-catalog-page__library">
                    <h2>Votre bibliothèque</h2>
                    <?php if ($inLibrary): ?>
                        <p class="hint">
                            Cette œuvre est dans
                            <strong><?= Moncine\View::escape(Moncine\LibraryStatut::label($libraryStatut)) ?></strong>.
                        </p>
                        <p>
                            <a href="/film.php?id=<?= (int) ($libraryEntry['id'] ?? 0) ?>" class="btn btn-primary">
                                Ouvrir ma fiche film
                            </a>
                        </p>
                    <?php else: ?>
                        <p class="hint">Cette œuvre n’est pas encore dans vos films ni dans vos envies.</p>
                        <p class="oeuvre-catalog-page__actions">
                            <a href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::COLLECTION, $oeuvreId)) ?>"
                               class="btn btn-secondary">Ajouter à mes films</a>
                            <a href="<?= Moncine\View::escape(Moncine\View::addFilmUrl(Moncine\LibraryStatut::WISHLIST, $oeuvreId)) ?>"
                               class="btn btn-secondary">Ajouter à mes envies</a>
                        </p>
                    <?php endif; ?>
                </section>

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
