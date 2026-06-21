<?php
/** @var array<string, mixed>|null $game */
/** @var list<array<string, mixed>> $magazineCoverage */
/** @var bool $saved */
/** @var bool $canManageCatalog */
/** @var int $gameId */
/** @var bool $isWishlist */
/** @var string $listBackUrl */
/** @var int|null $noteSur10 */
/** @var float|null $noteFoyerMoyenne */
/** @var string $addedAtLabel */
/** @var array<string, mixed>|null $baseGame */
/** @var list<array<string, mixed>> $extensions */

$canManageCatalog = $canManageCatalog ?? false;
$gameId = (int) ($gameId ?? 0);
$isWishlist = $isWishlist ?? false;
$listBackUrl = $listBackUrl ?? '/jeux.php';
$addedAtLabel = $addedAtLabel ?? '';
$testedOnLinux = !empty($game['tested_on_linux']);
$linuxNotSupported = !empty($game['linux_not_supported']);
$linuxBadge = (string) ($game['linux_badge'] ?? '');
if ($linuxBadge === '' && $testedOnLinux) {
    $linuxBadge = 'supported';
}
if ($linuxBadge === '' && $linuxNotSupported) {
    $linuxBadge = 'unsupported';
}
?>
<section class="collection-page">
    <?php if ($game === null): ?>
        <h1>Jeu introuvable</h1>
        <p class="hint">Ce jeu n’existe pas ou n’est pas accessible dans votre bibliothèque.</p>
        <p><a href="/jeux.php" class="btn btn-secondary">← Mes jeux</a></p>
    <?php else: ?>
        <?php
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $genreList = $game['genre_list'] ?? Moncine\GameGenre::parseList((string) ($game['genre'] ?? ''));
        $physicalLabels = $game['physical_support_labels'] ?? Moncine\GamePhysicalSupport::displayLabels((string) ($game['physical_supports'] ?? ''));
        ?>
        <p><a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a></p>

        <?php if ($saved): ?>
            <div class="alert alert-success">Jeu enregistré.</div>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <div class="alert alert-success">Jeu ajouté à votre collection.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['promote_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['note']) && (int) $_GET['note'] >= 1): ?>
            <div class="alert alert-success">Note enregistrée : <?= (int) $_GET['note'] ?>/10.</div>
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

        <?php if (!empty($canManageCatalog)): ?>
            <p class="game-detail-page__toolbar">
                <a href="<?= Moncine\View::escape(Moncine\View::gameEditUrl((int) ($game['id'] ?? 0))) ?>"
                   class="btn btn-secondary btn-sm">Modifier la fiche</a>
            </p>
        <?php endif; ?>

        <article class="film-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large" src="<?= $posterSrc ?>"
                     alt="Jaquette de <?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?>">
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
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
                        echo Moncine\View::escape($meta !== [] ? implode(' · ', $meta) : '');
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
                        <dd class="game-detail__platform-row">
                            <?= Moncine\View::escape((string) $game['platform_label']) ?>
                        </dd>
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

                    <?php if ($addedAtLabel !== ''): ?>
                        <dt><?= $isWishlist ? 'Envie ajoutée le' : 'Ajouté le' ?></dt>
                        <dd><?= Moncine\View::escape($addedAtLabel) ?></dd>
                    <?php endif; ?>

                    <?php if (!$isWishlist && (!empty($noteSur10) || ($noteFoyerMoyenne ?? null) !== null)): ?>
                        <dt>Notes</dt>
                        <dd>
                            <?php if (!empty($noteSur10)): ?>
                                <p class="film-ratings film-ratings--detail">
                                    <span class="film-ratings__label">Votre note</span>
                                    <span class="film-note" title="Votre note sur ce jeu"><?= (int) $noteSur10 ?>/10</span>
                                </p>
                            <?php endif; ?>
                            <?php if (($noteFoyerMoyenne ?? null) !== null): ?>
                                <p class="film-ratings film-ratings--detail">
                                    <span class="film-ratings__label">Moyenne du foyer</span>
                                    <span class="film-note film-note--foyer" title="Note moyenne des membres du foyer">
                                        <?= Moncine\View::escape(Moncine\HistoriqueRepository::formatAverageNote($noteFoyerMoyenne)) ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>

                <?php require MONCINE_ROOT . '/templates/_game_editions_display.php'; ?>

                <?php
                $gameRelatedSections = Moncine\GameRelatedSections::build(
                    $game,
                    $baseGame ?? null,
                    $originalGame ?? null,
                    $extensions ?? [],
                    $remakes ?? [],
                    static fn (array $row): string => Moncine\View::gameUrl((int) ($row['bib_id'] ?? 0)),
                );
                require MONCINE_ROOT . '/templates/_game_related_posters.php';
                ?>

                <?php if (trim((string) ($game['synopsis'] ?? '')) !== ''): ?>
                    <section>
                        <h2>Description</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $game['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>

                <?php if (Moncine\GameAttachmentRepository::isAvailable()): ?>
                    <?php
                    $attachments = $attachments ?? [];
                    require MONCINE_ROOT . '/templates/_game_attachments_panel.php';
                    ?>
                <?php endif; ?>

                <?php if (!empty($showIgdbEnrich)): ?>
                    <?php
                    $enrichTarget = 'game';
                    $entityId = $gameId;
                    $currentPosterUrl = (string) ($game['poster_url'] ?? '');
                    require MONCINE_ROOT . '/templates/_enrich_game_panel.php';
                    ?>
                <?php endif; ?>

                <section aria-labelledby="game-magazine-heading">
                    <h2 id="game-magazine-heading">Dans vos magazines</h2>
                    <?php if ($magazineCoverage === []): ?>
                        <p class="hint">
                            Aucun sujet magazine relié pour l’instant. Lors de l’ajout d’un test ou preview sur un numéro,
                            vous pourrez associer ce jeu à la fiche catalogue.
                        </p>
                    <?php else: ?>
                        <ul class="magazine-subject-results" role="list">
                            <?php foreach ($magazineCoverage as $row): ?>
                                <li class="magazine-subject-results__item" role="listitem">
                                    <a href="<?= Moncine\View::escape(Moncine\View::magazineIssueUrl((int) ($row['bib_id'] ?? 0))) ?>"
                                       class="magazine-subject-results__link">
                                        <span class="magazine-tag magazine-tag--subject">
                                            <?= Moncine\View::escape((string) ($row['category_label'] ?? '')) ?>
                                        </span>
                                        <strong><?= Moncine\View::escape((string) ($row['display_label'] ?? '')) ?></strong>
                                        <span class="hint">
                                            <?= Moncine\View::escape((string) ($row['series_titre'] ?? '')) ?>
                                            — n°<?= Moncine\View::escape((string) ($row['numero'] ?? '')) ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à ma collection</h2>
                        <p class="hint">Vous avez acheté ce jeu ? Il passera dans « Mes jeux ».</p>
                        <?php
                        $return = 'fiche';
                        require MONCINE_ROOT . '/templates/_game_promote_form.php';
                        ?>
                    </section>
                <?php else: ?>
                    <section class="marquer-vu-panel">
                        <h2 class="marquer-vu-panel__title">Votre note</h2>
                        <?php
                        $defaultNote = !empty($noteSur10) ? (int) $noteSur10 : null;
                        require MONCINE_ROOT . '/templates/_marquer_joue_form.php';
                        ?>
                    </section>
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
