<?php
/** @var array<string, mixed>|null $game */
/** @var list<array<string, mixed>> $magazineCoverage */
/** @var int $magazineIssueCount */
/** @var bool $saved */
/** @var bool $canManageCatalog */
/** @var int $gameId */
/** @var bool $isWishlist */
/** @var string $listBackUrl */
/** @var int|null $monRessenti */
/** @var string $addedAtLabel */
/** @var array<string, mixed>|null $baseGame */
/** @var list<array<string, mixed>> $extensions */
/** @var list<array<string, mixed>> $gameCompletions */
/** @var int $completionCount */

$canManageCatalog = $canManageCatalog ?? false;
$gameId = (int) ($gameId ?? 0);
$isWishlist = $isWishlist ?? false;
$listBackUrl = $listBackUrl ?? '/jeux.php';
$addedAtLabel = $addedAtLabel ?? '';
$gameCompletions = $gameCompletions ?? [];
$completionCount = (int) ($completionCount ?? 0);
$linuxBadge = (string) ($game['linux_badge'] ?? '');
if ($linuxBadge === '' && !empty($game['tested_on_linux'])) {
    $linuxBadge = 'supported';
}
if ($linuxBadge === '' && !empty($game['linux_not_supported'])) {
    $linuxBadge = 'unsupported';
}
?>
<section class="collection-page game-detail-page">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p class="hint">Ce jeu n’existe pas ou n’est pas accessible dans votre bibliothèque.</p>
        <p><a href="/jeux.php" class="btn btn-secondary">← Mes jeux</a></p>
    <?php else: ?>
        <?php
        $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        ?>
        <p><a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a></p>

        <?php if ($saved): ?>
            <div class="alert alert-success">Exemplaire enregistré.</div>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <div class="alert alert-success">Jeu ajouté à votre collection.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['promote_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['fin'])): ?>
            <div class="alert alert-success">
                Fin de partie enregistrée<?php if (!empty($_GET['date'])): ?>
                    (<?= Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $_GET['date'])) ?>)
                <?php endif; ?>.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['fin_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['fin_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['note']) && Moncine\RessentiNote::normalizeScore((int) $_GET['note']) !== null): ?>
            <div class="alert alert-success">Ressenti enregistré : <?= Moncine\View::escape(Moncine\View::ressentiLabel((int) $_GET['note'])) ?>.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['note_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['note_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['delete_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['delete_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['attachment']) && (string) $_GET['attachment'] === '1'): ?>
            <div class="alert alert-success">Fichier ajouté.</div>
        <?php endif; ?>
        <?php if (isset($_GET['attachment_deleted']) && (string) $_GET['attachment_deleted'] === '1'): ?>
            <div class="alert alert-success">Fichier supprimé.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['attachment_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['attachment_error']) ?></p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce jeu est dans vos envies (pas encore dans votre collection).</p>
        <?php endif; ?>

        <?php // Actions centralisées sous le temps de jeu (sidebar). ?>

        <article class="film-detail game-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php require MONCINE_ROOT . '/templates/_game_detail_sidebar.php'; ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? 'Jeu')) ?></span>
                        <?php if ($linuxBadge !== ''): ?>
                            <?php
                            $size = 'md';
                            $plain = true;
                            require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
                            ?>
                        <?php endif; ?>
                        <?php if ((int) ($game['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $game['annee'] ?>)</span>
                        <?php endif; ?>
                        <?php if (!$isWishlist || !empty($monRessenti)): ?>
                            <?php require MONCINE_ROOT . '/templates/_game_detail_ressenti_title.php'; ?>
                        <?php endif; ?>
                    </h1>
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

                <section class="game-detail__facts" aria-labelledby="game-facts-heading">
                    <h2 id="game-facts-heading" class="game-detail__section-title">Détails</h2>
                    <?php require MONCINE_ROOT . '/templates/_game_detail_facts_columns.php'; ?>

                    <?php if (trim((string) ($game['synopsis'] ?? '')) !== ''): ?>
                        <h3 class="stats-subtitle">Description</h3>
                        <p class="game-detail__synopsis"><?= nl2br(Moncine\View::escape((string) $game['synopsis'])) ?></p>
                    <?php endif; ?>
                </section>

                <?php
                $gameRelatedSections = Moncine\GameRelatedSections::build(
                    $game,
                    $baseGame ?? null,
                    $originalGame ?? null,
                    $extensions ?? [],
                    $remakes ?? [],
                    static fn (array $row): string => (string) ($row['library_url'] ?? ''),
                    $franchiseGames ?? [],
                );
                if ($gameRelatedSections !== []):
                    ?>
                    <section class="game-detail__related" aria-label="Jeux liés">
                        <?php require MONCINE_ROOT . '/templates/_game_related_posters.php'; ?>
                    </section>
                <?php endif; ?>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à ma collection</h2>
                        <p class="hint">Vous avez acheté ce jeu ? Il passera dans « Mes jeux ».</p>
                        <?php
                        $return = 'fiche';
                        require MONCINE_ROOT . '/templates/_game_promote_form.php';
                        ?>
                    </section>
                <?php endif; ?>

                <?php
                $magazineIssueCount = (int) ($magazineIssueCount ?? count($magazineCoverage ?? []));
                $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
                require MONCINE_ROOT . '/templates/_game_magazines_link.php';
                ?>

                <?php if (!empty($showIgdbEnrich)): ?>
                    <?php
                    $enrichTarget = 'game';
                    $entityId = $gameId;
                    $currentPosterUrl = (string) ($game['poster_url'] ?? '');
                    require MONCINE_ROOT . '/templates/_enrich_game_panel.php';
                    ?>
                <?php endif; ?>

                <?php if (Moncine\GameAttachmentRepository::isAvailable()): ?>
                    <?php
                    $attachments = $attachments ?? [];
                    require MONCINE_ROOT . '/templates/_game_attachments_panel.php';
                    ?>
                <?php endif; ?>

                <div class="result-actions result-actions--with-delete">
                    <a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-ghost">Retour à la liste</a>
                    <?php
                    $deleteTitle = $isWishlist ? 'Retirer des envies' : 'Supprimer de mes jeux';
                    $deleteConfirm = $isWishlist
                        ? 'Retirer « ' . (string) ($game['display_titre'] ?? $game['titre'] ?? '') . ' » de vos envies ?'
                        : 'Supprimer définitivement « ' . (string) ($game['display_titre'] ?? $game['titre'] ?? '') . ' » ?';
                    ?>
                    <form method="post" action="/supprimer-jeu.php" class="inline-form game-detail__delete-form"
                          onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="game_id" value="<?= $gameId ?>">
                        <button type="submit"
                                class="btn btn-icon btn-danger-text btn-sm"
                                title="<?= Moncine\View::escape($deleteTitle) ?>"
                                aria-label="<?= Moncine\View::escape($deleteTitle) ?>">
                            <svg class="icon-trash" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
