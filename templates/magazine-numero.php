<?php
/** @var array<string, mixed>|null $issue */
/** @var bool $saved */
/** @var string $error */
/** @var string $dateLabel */
/** @var string $pdfUrl */
/** @var string $popoverOpen */
?>
<section class="collection-page game-detail-page">
    <?php if ($issue === null): ?>
        <h1>Numéro introuvable</h1>
        <p><a href="/magazines.php" class="btn btn-secondary">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    <?php else: ?>
        <?php
        $bibId = (int) ($issue['bib_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $pageStatut = (string) ($issue['statut'] ?? Moncine\LibraryStatut::COLLECTION);
        $isWishlistIssue = $pageStatut === Moncine\LibraryStatut::WISHLIST;
        $issuePageUrl = Moncine\View::magazineIssueUrl($bibId);
        $deleteMode = isset($_GET['supprimer']);
        $deleteModeUrl = $issuePageUrl . '&supprimer=1';
        $popoverOpen = (string) ($popoverOpen ?? '');
        $seriesBackUrl = Moncine\View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', ['statut' => $pageStatut]);
        ?>

        <div class="magazine-issue-toolbar">
            <p class="magazine-issue-toolbar__back">
                <a href="<?= Moncine\View::escape($seriesBackUrl) ?>" class="btn btn-secondary btn-sm">
                    ← <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Série')) ?>
                </a>
            </p>
            <?php if (!$deleteMode): ?>
                <a href="<?= Moncine\View::escape($deleteModeUrl) ?>"
                   class="btn btn-icon btn-danger-text magazine-issue-toolbar__delete-toggle"
                   title="Mode suppression"
                   aria-label="Activer le mode suppression pour retirer ce numéro">
                    <svg class="icon-trash" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($deleteMode): ?>
            <div class="magazine-delete-mode-panel" role="region" aria-label="Mode suppression">
                <p class="magazine-delete-mode-panel__title"><strong>Mode suppression</strong></p>
                <p class="hint">
                    <?php if ($isWishlistIssue): ?>
                        Ce numéro sera retiré de vos envies. Il restera visible dans votre collection si vous l’y aviez déjà référencé.
                    <?php else: ?>
                        Ce numéro sera retiré de vos magazines (collection du foyer). Cette action ne supprime pas le fichier PDF du serveur si un PDF était importé.
                    <?php endif; ?>
                </p>
                <div class="magazine-delete-mode-panel__actions">
                    <?php
                    $possessionFilter = Moncine\MagazineRepository::POSSESSION_ALL;
                    $variant = 'panel';
                    require MONCINE_ROOT . '/templates/_magazine_delete_button.php';
                    ?>
                    <a href="<?= Moncine\View::escape($issuePageUrl) ?>" class="btn btn-secondary btn-sm">Annuler</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Numéro retiré de votre liste.</div>
        <?php endif; ?>
        <?php if ($saved || isset($_GET['added'])): ?>
            <div class="alert alert-success">
                Numéro enregistré.
                <?php if (isset($_GET['pdf'])): ?>
                    Le PDF a bien été importé.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['pdf_removed'])): ?>
            <div class="alert alert-success">Le PDF a été retiré de ce numéro.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
        <?php endif; ?>
        <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

        <article class="film-detail game-detail film-detail--with-poster">
            <?php require MONCINE_ROOT . '/templates/_magazine_detail_sidebar.php'; ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <div class="game-detail__title-bar">
                        <h1 class="game-detail__title-row">
                            <span><?= Moncine\View::escape((string) ($issue['series_titre'] ?? '')) ?></span>
                        </h1>
                    </div>
                    <p class="game-detail__saga">
                        Numéro <strong><?= Moncine\View::escape((string) ($issue['numero'] ?? '')) ?></strong>
                        · <?= Moncine\View::escape($dateLabel) ?>
                        <?php if ((int) ($issue['pages'] ?? 0) > 0): ?>
                            · <?= (int) $issue['pages'] ?> p.
                        <?php endif; ?>
                        <?php require MONCINE_ROOT . '/templates/_magazine_support_tags.php'; ?>
                        <?php if (($issue['statut'] ?? '') === Moncine\LibraryStatut::COLLECTION && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                            <span class="magazine-tag magazine-tag--none">Non possédé</span>
                        <?php endif; ?>
                    </p>
                </header>

                <?php if (($issue['statut'] ?? '') === Moncine\LibraryStatut::COLLECTION && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                    <div class="magazine-unowned-actions">
                        <p class="hint">Ce numéro est référencé mais vous ne l’avez ni en papier ni en PDF — il n’est pas compté parmi vos numéros possédés.</p>
                        <?php
                        $possessionFilter = Moncine\MagazineRepository::POSSESSION_ALL;
                        require MONCINE_ROOT . '/templates/_magazine_wishlist_button.php';
                        ?>
                    </div>
                <?php elseif ((int) ($issue['in_wishlist'] ?? 0) > 0 && !Moncine\MagazineSupport::isPossessed($issue)): ?>
                    <p class="hint"><span class="magazine-tag magazine-tag--wishlist">En envies</span> — également listé dans vos envies.</p>
                <?php endif; ?>

                <section class="game-detail__facts" aria-labelledby="magazine-sommaire-heading">
                    <h2 id="magazine-sommaire-heading" class="game-detail__section-title">Sommaire</h2>
                    <?php if (trim((string) ($issue['sommaire'] ?? '')) !== ''): ?>
                        <div class="game-detail__synopsis magazine-sommaire__body"><?= Moncine\View::escape((string) $issue['sommaire']) ?></div>
                    <?php else: ?>
                        <p class="hint">Aucun sommaire renseigné.</p>
                    <?php endif; ?>
                </section>

                <?php require MONCINE_ROOT . '/templates/_magazine_issue_subjects.php'; ?>

                <div class="result-actions">
                    <a href="<?= Moncine\View::escape($seriesBackUrl) ?>" class="btn btn-ghost">Retour à la série</a>
                </div>
            </div>
        </article>
    <?php endif; ?>
</section>
