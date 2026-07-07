<?php
/**
 * Fiche catalogue — jeu vidéo (affichage aligné sur la fiche bibliothèque + outils admin).
 *
 * @var array<string, mixed>|null $game
 * @var array<string, mixed>|null $library
 * @var int|null $libraryBibId
 * @var int $libraryCount
 * @var string $catalogueBackUrl
 * @var array<string, mixed>|null $baseGame
 * @var list<array<string, mixed>> $catalogExtensions
 * @var list<array<string, mixed>> $gameCompletions
 * @var int $completionCount
 */
$navLabels = Moncine\MediaDomain::navLabels(Moncine\MediaDomain::JEU);
$gameCompletions = $gameCompletions ?? [];
$completionCount = (int) ($completionCount ?? 0);
?>
<section class="oeuvre-catalog-page oeuvre-catalog-page--game game-detail-page">
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
        ?>
        <p class="breadcrumb">
            <?php
            $profileUserId = (int) ($_GET['profile_user'] ?? 0);
            $pageBackUrl = Moncine\View::catalogOeuvrePageBackUrl($catalogueBackUrl, $profileUserId, Moncine\MediaDomain::JEU);
            $backLabel = $profileUserId > 0 ? 'Profil' : (Moncine\CatalogAdmin::canAccess() ? 'Catalogue' : 'Mes jeux');
            ?>
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>"><?= Moncine\View::escape($backLabel) ?></a>
            <span aria-hidden="true"> › </span>
            <span><?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?></span>
        </p>

        <?php if (Moncine\CatalogAdmin::canAccess()): ?>
            <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

            <p class="hint oeuvre-catalog-page__badge">
                Fiche catalogue jeu vidéo (ID <?= $oeuvreId ?>)
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

        <?php if (!empty($saved)): ?>
            <div class="alert alert-success">Modifications enregistrées.</div>
        <?php endif; ?>
        <?php if (!empty($posterUploaded)): ?>
            <div class="alert alert-success">Jaquette enregistrée.</div>
        <?php endif; ?>

        <article class="film-detail game-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php
            // Fiche catalogue : si l’œuvre n’est pas dans la bibliothèque, on remplace les actions rapides
            // par « ajouter aux envies » / « ajouter à la bibliothèque ».
            $isInLibrary = $inLibrary;
            require MONCINE_ROOT . '/templates/_game_detail_sidebar.php';
            ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <div class="game-detail__title-bar">
                        <h1 class="game-detail__title-row">
                            <span><?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?></span>
                            <?php if (!empty($game['is_extension'])): ?>
                                <span class="magazine-tag">Extension</span>
                            <?php endif; ?>
                            <?php if (!empty($game['is_remake'])): ?>
                                <span class="magazine-tag">Remake</span>
                            <?php endif; ?>
                            <?php
                            $linuxBadge = (string) ($game['linux_badge'] ?? '');
                            if ($linuxBadge !== ''):
                                $size = 'md';
                                $plain = true;
                                require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
                            endif;
                            ?>
                            <?php if ((int) ($game['annee'] ?? 0) > 0): ?>
                                <span class="film-year">(<?= (int) $game['annee'] ?>)</span>
                            <?php endif; ?>
                        </h1>
                    </div>
                    <?php
                    $franchiseName = Moncine\GameRelatedSections::resolveFranchiseName(
                        $game,
                        $baseGame ?? null,
                        $originalGame ?? null,
                    );
                    if ($franchiseName !== ''):
                        ?>
                        <p class="game-detail__saga">
                            <span class="game-detail__saga-label">Saga</span>
                            <?php require MONCINE_ROOT . '/templates/_game_franchise_link.php'; ?>
                        </p>
                    <?php endif; ?>
                </header>

                <section class="game-detail__facts" aria-labelledby="catalog-game-facts-heading">
                    <h2 id="catalog-game-facts-heading" class="game-detail__section-title">Détails</h2>
                    <?php require MONCINE_ROOT . '/templates/_game_detail_facts_columns.php'; ?>

                    <?php if ((string) ($game['edition_summary'] ?? '') !== ''): ?>
                        <h3 class="stats-subtitle">Éditions catalogue</h3>
                        <p class="game-detail__synopsis"><?= Moncine\View::escape((string) $game['edition_summary']) ?></p>
                    <?php endif; ?>

                    <?php if (trim((string) ($game['synopsis'] ?? '')) !== ''): ?>
                        <h3 class="stats-subtitle">Description</h3>
                        <p class="game-detail__synopsis"><?= nl2br(Moncine\View::escape((string) $game['synopsis'])) ?></p>
                    <?php endif; ?>
                </section>

                <?php require MONCINE_ROOT . '/templates/_catalog_game_store_links_display.php'; ?>

                <?php
                $gameRelatedSections = Moncine\GameRelatedSections::build(
                    $game,
                    $baseGame ?? null,
                    $originalGame ?? null,
                    $catalogExtensions ?? [],
                    $catalogRemakes ?? [],
                    static fn (array $row): string => trim((string) ($row['library_url'] ?? '')) !== ''
                        ? (string) $row['library_url']
                        : Moncine\View::oeuvreJeuUrl(
                        (int) ($row['oeuvre_id'] ?? 0),
                        $catalogSearch ?? '',
                        $catalogSort ?? 'titre',
                        $catalogDir ?? 'asc',
                        (int) ($catalogPage ?? 1),
                    ),
                    $franchiseGames ?? [],
                );
                if ($gameRelatedSections !== []):
                    ?>
                    <section class="game-detail__related" aria-label="Jeux liés">
                        <?php require MONCINE_ROOT . '/templates/_game_related_posters.php'; ?>
                    </section>
                <?php endif; ?>

                <?php $catalogMagazineSubjects = $catalogMagazineSubjects ?? []; ?>
                <?php if (Moncine\CatalogAdmin::canAccess() && $catalogMagazineSubjects !== []): ?>
                    <section class="game-detail__magazines" aria-labelledby="catalog-game-magazine-heading">
                        <h2 id="catalog-game-magazine-heading" class="game-detail__section-title">Sujets magazine reliés</h2>
                        <p class="hint">
                            Sujets test / preview / interview du catalogue magazines pointant vers cette fiche jeu
                            (tous numéros du catalogue, pas seulement votre bibliothèque).
                        </p>
                        <ul class="magazine-subject-results" role="list">
                            <?php foreach ($catalogMagazineSubjects as $row): ?>
                                <?php $subjectId = (int) ($row['subject_id'] ?? 0); ?>
                                <li class="magazine-subject-results__item" role="listitem">
                                    <a href="<?= Moncine\View::escape(Moncine\View::magazineSubjectNavUrl($subjectId)) ?>"
                                       class="magazine-subject-results__link">
                                        <span class="magazine-tag magazine-tag--subject">
                                            <?= Moncine\View::escape((string) ($row['category_label'] ?? '')) ?>
                                        </span>
                                        <strong><?= Moncine\View::escape((string) ($row['display_label'] ?? '')) ?></strong>
                                        <span class="hint">
                                            <?= (int) ($row['issue_count'] ?? 0) ?> numéro<?= (int) ($row['issue_count'] ?? 0) > 1 ? 's' : '' ?> catalogue
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="hint">
                            <a href="/maintenance-magazine-jeux-liens.php">Gérer les liens magazine ↔ jeux</a>
                        </p>
                    </section>
                <?php endif; ?>

                <?php if (Moncine\CatalogAdmin::canAccess()): ?>
                <section class="oeuvre-catalog-page__admin-tools" aria-labelledby="catalog-game-admin-heading">
                    <h2 id="catalog-game-admin-heading" class="game-detail__section-title">Administration catalogue</h2>

                    <?php require MONCINE_ROOT . '/templates/_catalog_game_store_links_panel.php'; ?>

                    <?php require MONCINE_ROOT . '/templates/_oeuvre_jeu_edit_form.php'; ?>

                    <?php
                    $enrichTarget = 'oeuvre';
                    $entityId = $oeuvreId;
                    $hasIgdbCredentials = Moncine\IgdbConfig::hasCredentials() && Moncine\GameRepository::hasIgdbColumns();
                    $currentIgdbId = (int) ($game['igdb_id'] ?? 0);
                    $currentPosterUrl = (string) ($game['poster_url'] ?? '');
                    require MONCINE_ROOT . '/templates/_enrich_game_panel.php';
                    ?>

                    <?php
                    $posterUploadError = $posterUploadError ?? '';
                    $posterUploadOpen = $posterUploadOpen ?? false;
                    require MONCINE_ROOT . '/templates/_oeuvre_poster_upload_form.php';
                    ?>

                    <?php
                    $currentOeuvreId = $oeuvreId;
                    $currentOeuvreTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
                    require MONCINE_ROOT . '/templates/_catalog_oeuvre_merge_panel.php';
                    ?>
                </section>
                <?php endif; ?>

                <?php if (isset($catalogListContext, $oeuvreNav) && $oeuvreNav !== null): ?>
                    <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_nav.php'; ?>
                <?php endif; ?>
            </div>
        </article>

        <p class="collection-page__footer-links">
            <a href="<?= Moncine\View::escape($pageBackUrl) ?>">← Retour</a>
        </p>
    <?php endif; ?>
</section>
