<?php
/**
 * Fiche catalogue — numéro de magazine.
 *
 * @var array<string, mixed>|null $issue
 * @var array<string, mixed>|null $library
 * @var int|null $libraryBibId
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 * @var string $dateLabel
 */
$navLabels = Moncine\MediaDomain::navLabels(Moncine\MediaDomain::MAGAZINE);
?>
<section class="oeuvre-catalog-page oeuvre-catalog-page--magazine game-detail-page">
    <?php if ($issue === null): ?>
        <h1>Numéro introuvable</h1>
        <p>Cette fiche n’existe pas ou a été supprimée du catalogue.</p>
        <?php
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::MAGAZINE);
        ?>
        <a href="<?= Moncine\View::escape($pageBackUrl) ?>" class="btn btn-primary">Retour</a>
    <?php else:
        $oeuvreId = (int) ($issue['oeuvre_id'] ?? $oeuvreId ?? 0);
        $libraryEntry = $library;
        $inLibrary = $libraryEntry !== null && ($libraryBibId ?? 0) > 0;
        $posterSrc = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
        if ($posterSrc === '') {
            $posterSrc = Moncine\View::posterSrc(trim((string) ($issue['series_poster_url'] ?? '')) ?: null);
        }
        $profileUserId = (int) ($_GET['profile_user'] ?? 0);
        $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::MAGAZINE);
        $backLabel = $profileUserId > 0 ? 'Profil' : (Moncine\CatalogAdmin::canAccess() ? 'Catalogue' : 'Mes magazines');
        ?>
        <p class="breadcrumb">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>"><?= Moncine\View::escape($backLabel) ?></a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?> — n°<?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></span>
        </p>

        <?php if (Moncine\CatalogAdmin::canAccess()): ?>
            <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

            <p class="hint oeuvre-catalog-page__badge">
                Fiche catalogue magazine (ID <?= $oeuvreId ?>)
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
            $mediaDomain = Moncine\MediaDomain::MAGAZINE;
            $posterAlt = 'Couverture de ' . (string) ($issue['series_titre'] ?? '');
            $openLibraryLabel = 'Ouvrir ma fiche numéro';
            require MONCINE_ROOT . '/templates/_catalog_oeuvre_poster_sidebar.php';
            ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading">
                    <h1><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></h1>
                    <p class="lead">
                        Numéro <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                        · <?= Moncine\View::escape($dateLabel) ?>
                        <?php if ((int) ($issue['pages'] ?? 0) > 0): ?>
                            · <?= (int) $issue['pages'] ?> p.
                        <?php endif; ?>
                        <?php if (!empty($issue['est_hors_serie'])): ?>
                            · <span class="magazine-tag">Hors-série</span>
                        <?php endif; ?>
                    </p>
                </header>

                <dl class="film-facts">
                    <?php if ((string) ($issue['editeur'] ?? '') !== ''): ?>
                        <dt>Éditeur</dt>
                        <dd><?= Moncine\View::escape((string) $issue['editeur']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) ($issue['issn'] ?? '') !== ''): ?>
                        <dt>ISSN</dt>
                        <dd><?= Moncine\View::escape((string) $issue['issn']) ?></dd>
                    <?php endif; ?>

                    <?php if ((string) ($issue['publication_type'] ?? '') !== ''): ?>
                        <dt>Périodicité</dt>
                        <dd><?= Moncine\View::escape(Moncine\PublicationType::label((string) $issue['publication_type'])) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if (trim((string) ($issue['sommaire'] ?? '')) !== ''): ?>
                    <h2>Sommaire</h2>
                    <p class="film-synopsis magazine-sommaire"><?= Moncine\View::escape((string) $issue['sommaire']) ?></p>
                <?php endif; ?>

                <?php if (!empty($saved)): ?>
                    <p class="alert alert-success">Modifications enregistrées.</p>
                <?php endif; ?>
                <?php if (!empty($posterUploaded)): ?>
                    <p class="alert alert-success">Couverture enregistrée.</p>
                <?php endif; ?>
                <?php if (($saveError ?? '') !== ''): ?>
                    <p class="alert alert-warning"><?= Moncine\View::escape((string) $saveError) ?></p>
                <?php endif; ?>

                <?php if (Moncine\CatalogAdmin::canAccess()): ?>
                    <?php require MONCINE_ROOT . '/templates/_oeuvre_magazine_edit_form.php'; ?>

                    <?php
                    $posterUploadError = $posterUploadError ?? '';
                    $posterUploadOpen = $posterUploadOpen ?? false;
                    require MONCINE_ROOT . '/templates/_oeuvre_poster_upload_form.php';
                    ?>

                    <?php
                    $currentOeuvreId = $oeuvreId;
                    $currentOeuvreTitle = (string) ($issue['titre'] ?? '');
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
