<?php
/**
 * Fiche catalogue (œuvre partagée).
 *
 * @var array<string, mixed>|null $oeuvre
 * @var array<string, mixed>|null $library
 * @var int|null $libraryBibId
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 */
?>
<section class="oeuvre-catalog-page game-detail-page">
    <?php if ($oeuvre === null): ?>
        <h1>Œuvre introuvable</h1>
        <p>Cette fiche n’existe pas ou a été supprimée du catalogue.</p>
        <?php
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::FILM);
        ?>
        <a href="<?= Moncine\View::escape($pageBackUrl) ?>" class="btn btn-primary">Retour</a>
    <?php else:
        $oeuvreId = (int) ($oeuvre['id'] ?? 0);
        $libraryEntry = $library;
        $inLibrary = $libraryEntry !== null && ($libraryBibId ?? 0) > 0;
        $posterSrc = Moncine\View::posterSrc($oeuvre['poster_url'] ?? null);
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::FILM);
        $backLabel = $profileUserId > 0 ? 'Profil' : (Moncine\CatalogAdmin::canAccess() ? 'Catalogue' : 'Mes films');
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>"><?= Moncine\View::escape($backLabel) ?></a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?></span>
        </p>

        <?php if (Moncine\CatalogAdmin::canAccess()): ?>
            <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

            <p class="hint oeuvre-catalog-page__badge">
                Fiche catalogue partagée (ID <?= $oeuvreId ?>)
                <?php if ($libraryCount > 0): ?>
                    — <?= $libraryCount ?> entrée<?= $libraryCount > 1 ? 's' : '' ?> bibliothèque
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if (isset($catalogListContext, $oeuvreNav) && $oeuvreNav !== null): ?>
            <div id="catalog-oeuvre-nav" class="catalog-oeuvre-nav-anchor">
                <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
            </div>
        <?php endif; ?>

        <article class="film-detail game-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php
            $mediaDomain = Moncine\MediaDomain::FILM;
            $posterAlt = 'Affiche de ' . (string) ($oeuvre['titre'] ?? '');
            $openLibraryLabel = 'Ouvrir ma fiche film';
            require MONCINE_ROOT . '/templates/_catalog_oeuvre_poster_sidebar.php';
            ?>

            <div class="film-detail__body game-detail__body">
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

                <?php if (Moncine\CatalogAdmin::canAccess()): ?>
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

                    <?php require MONCINE_ROOT . '/templates/_oeuvre_eans.php'; ?>

                    <?php
                    $currentOeuvreId = $oeuvreId;
                    $currentOeuvreTitle = (string) ($oeuvre['titre'] ?? '');
                    $mediaDomain = Moncine\MediaDomain::FILM;
                    require MONCINE_ROOT . '/templates/_catalog_oeuvre_merge_panel.php';
                    ?>

                    <?php if (isset($catalogListContext, $oeuvreNav) && $oeuvreNav !== null): ?>
                        <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>">← Retour</a>
        </p>
    <?php endif; ?>
</section>
