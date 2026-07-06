<?php
/**
 * Fiche catalogue — album BD / manga.
 *
 * @var array<string, mixed>|null $album
 * @var array<string, mixed>|null $library
 * @var int|null $libraryBibId
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 * @var int $oeuvreId
 */
$navLabels = Moncine\MediaDomain::navLabels(Moncine\MediaDomain::BD);
?>
<section class="oeuvre-catalog-page oeuvre-catalog-page--bd game-detail-page">
    <?php if ($album === null): ?>
        <h1>Album introuvable</h1>
        <p class="hint">Cette fiche catalogue n’existe pas.</p>
        <?php
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::BD);
        ?>
        <p><a href="<?= Moncine\View::escape($pageBackUrl) ?>" class="btn btn-secondary">← Retour</a></p>
    <?php else:
        $oeuvreId = (int) ($oeuvreId ?? $album['oeuvre_id'] ?? 0);
        $libraryEntry = $library;
        $inLibrary = $libraryEntry !== null && ($libraryBibId ?? 0) > 0;
        $posterSrc = Moncine\View::posterSrc($album['poster_url'] ?? null);
        $seriesTitre = trim((string) ($album['series_titre'] ?? ''));
        $tomeNumero = (int) ($album['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($album['tome_label'] ?? ''));
        $numeroLabel = Moncine\BdRowMapper::tomeNumeroLabel($tomeNumero, $tomeLabel);
        $albumTitle = trim((string) ($album['titre'] ?? ''));
        $h1Title = $albumTitle !== '' ? $albumTitle : (string) ($album['display_titre'] ?? 'Album');
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::BD);
        $backLabel = $profileUserId > 0 ? 'Profil' : (Moncine\CatalogAdmin::canAccess() ? 'Catalogue' : 'Mes BD');
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>"><?= Moncine\View::escape($backLabel) ?></a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape($h1Title) ?></span>
        </p>

        <?php if (Moncine\CatalogAdmin::canAccess()): ?>
            <p class="hint oeuvre-catalog-page__badge">
                Fiche catalogue BD (ID <?= $oeuvreId ?>)
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
            $mediaDomain = Moncine\MediaDomain::BD;
            $openLibraryLabel = 'Ouvrir ma fiche album';
            require MONCINE_ROOT . '/templates/_catalog_oeuvre_poster_sidebar.php';
            ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading">
                    <h1>
                        <?= Moncine\View::escape($h1Title) ?>
                        <?php if ($numeroLabel !== ''): ?>
                            <span class="film-year"><?= Moncine\View::escape($numeroLabel) ?></span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($seriesTitre !== ''): ?>
                        <p class="film-original-title"><?= Moncine\View::escape($seriesTitre) ?></p>
                    <?php endif; ?>
                </header>

                <dl class="film-facts">
                    <?php if ((string) ($album['kind_label'] ?? '') !== ''): ?>
                        <div><dt>Type</dt><dd><?= Moncine\View::escape((string) $album['kind_label']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ((string) ($album['scenariste'] ?? '') !== ''): ?>
                        <div><dt>Scénariste</dt><dd><?= Moncine\View::escape((string) $album['scenariste']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ((string) ($album['dessinateur'] ?? '') !== ''): ?>
                        <div><dt>Dessinateur</dt><dd><?= Moncine\View::escape((string) $album['dessinateur']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ((string) ($album['editeur'] ?? '') !== ''): ?>
                        <div><dt>Éditeur</dt><dd><?= Moncine\View::escape((string) $album['editeur']) ?></dd></div>
                    <?php endif; ?>
                </dl>

                <?php if (trim((string) ($album['synopsis'] ?? '')) !== ''): ?>
                    <h2>Résumé</h2>
                    <p class="film-synopsis"><?= Moncine\View::escape((string) $album['synopsis']) ?></p>
                <?php endif; ?>
            </div>
        </article>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>">← Retour</a>
        </p>
    <?php endif; ?>
</section>
