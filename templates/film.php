<?php
/** @var array<string, mixed>|null $film */
/** @var int $filmId */
/** @var bool $isWishlist */
/** @var string $listBackUrl */
/** @var int|null $monRessenti */
/** @var list<array<string, mixed>> $sagaFilms */
/** @var string $popoverOpen */

$filmId = (int) ($filmId ?? 0);
$isWishlist = $isWishlist ?? (($film['statut'] ?? '') === Moncine\LibraryStatut::WISHLIST);
$listBackUrl = $listBackUrl ?? ($isWishlist ? '/souhaits.php' : '/films.php');
$popoverOpen = (string) ($popoverOpen ?? '');
$sagaFilms = $sagaFilms ?? [];
?>
<section class="collection-page game-detail-page">
    <?php if ($film === null): ?>
        <h1>Film introuvable</h1>
        <p class="hint">Ce film n’existe pas ou a été supprimé.</p>
        <p><a href="/films.php" class="btn btn-secondary">← Mes films</a></p>
    <?php else: ?>
        <?php $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null); ?>

        <p><a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-secondary btn-sm">← Retour à la liste</a></p>

        <?php if (isset($_GET['vu'])): ?>
            <div class="alert alert-success">
                Vision enregistrée<?= !empty($_GET['vu_date'])
                    ? ' le ' . Moncine\View::escape((string) $_GET['vu_date'])
                    : '' ?><?php if (!empty($_GET['vu_note'])):
                    $vuScore = Moncine\RessentiNote::normalizeScore((int) $_GET['vu_note']);
                    if ($vuScore !== null): ?>
                    — ressenti : <?= Moncine\View::escape(Moncine\View::ressentiLabel($vuScore)) ?>
                    <?php endif; endif ?>.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['vu_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vu_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['vision_supprimee'])): ?>
            <p class="alert alert-success">Date de vision supprimée de l’historique.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['vision_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['vision_error']) ?></p>
        <?php endif; ?>
        <?php if (!empty($_GET['delete_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['delete_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <p class="alert alert-success">Ce film fait maintenant partie de vos films.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['promote_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['promote_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['added']) && (string) $_GET['added'] === '1'): ?>
            <p class="alert alert-success">Film ajouté avec succès.</p>
        <?php endif; ?>
        <?php if (!empty($saved)): ?>
            <p class="alert alert-success">Fiche enregistrée.</p>
        <?php endif; ?>
        <?php if (isset($_GET['note']) && Moncine\RessentiNote::normalizeScore((int) $_GET['note']) !== null): ?>
            <div class="alert alert-success">Ressenti enregistré : <?= Moncine\View::escape(Moncine\View::ressentiLabel((int) $_GET['note'])) ?>.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['note_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['note_error']) ?></p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce film est dans vos envies (pas encore dans vos films).</p>
            <?php if (Moncine\WishlistTargetRepository::tableExists()): ?>
                <?php require MONCINE_ROOT . '/templates/_wishlist_targets_panel.php'; ?>
            <?php endif; ?>
        <?php endif; ?>

        <article class="film-detail game-detail<?= $posterSrc !== '' ? ' film-detail--with-poster' : '' ?>" id="film-detail">
            <?php require MONCINE_ROOT . '/templates/_film_detail_sidebar.php'; ?>

            <div class="film-detail__body game-detail__body">
                <?php if (isset($filmListContext) && isset($filmNav)): ?>
                    <div id="film-list-nav" class="film-list-nav-anchor film-list-nav-anchor--top">
                        <?php require MONCINE_ROOT . '/templates/_film_list_nav.php'; ?>
                    </div>
                <?php endif; ?>

                <header class="film-detail__heading game-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></span>
                        <?php if ((int) ($film['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $film['annee'] ?>)</span>
                        <?php endif; ?>
                        <?php if (!$isWishlist || !empty($monRessenti)): ?>
                            <?php require MONCINE_ROOT . '/templates/_game_detail_ressenti_title.php'; ?>
                        <?php endif; ?>
                    </h1>
                    <?php if (trim((string) ($film['titre_original'] ?? '')) !== ''): ?>
                        <p class="film-original-title" lang="und">
                            <?= Moncine\View::escape((string) $film['titre_original']) ?>
                        </p>
                    <?php endif; ?>
                    <?php
                    $sagaName = trim((string) ($film['saga'] ?? ''));
                    if ($sagaName !== ''):
                        $sagaOrdre = (int) ($film['saga_ordre'] ?? 0);
                        ?>
                        <p class="game-detail__saga">
                            <span class="game-detail__saga-label">Saga</span>
                            <?php require MONCINE_ROOT . '/templates/_saga_link.php'; ?>
                        </p>
                    <?php endif; ?>
                </header>

                <section class="game-detail__facts" aria-labelledby="film-facts-heading">
                    <h2 id="film-facts-heading" class="game-detail__section-title">Détails</h2>
                    <?php require MONCINE_ROOT . '/templates/_film_detail_facts_columns.php'; ?>

                    <?php if (!empty($film['synopsis'])): ?>
                        <h3 class="stats-subtitle">Synopsis</h3>
                        <p class="game-detail__synopsis"><?= Moncine\View::escape($film['synopsis']) ?></p>
                    <?php endif; ?>
                </section>

                <?php
                $gameRelatedSections = Moncine\GameRelatedSections::build(
                    ['is_extension' => false, 'is_remake' => false],
                    null,
                    null,
                    [],
                    [],
                    static fn (array $row): string => (string) ($row['library_url'] ?? ''),
                    $sagaFilms,
                );
                if ($gameRelatedSections !== []):
                    ?>
                    <section class="game-detail__related" aria-label="Films de la saga">
                        <?php require MONCINE_ROOT . '/templates/_game_related_posters.php'; ?>
                    </section>
                <?php endif; ?>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à mes films</h2>
                        <p class="hint">Vous avez acheté ce film ? Il passera dans « Mes films ».</p>
                        <?php
                        $wishlistTargets = $wishlistTargets ?? [];
                        $includeListContext = true;
                        require MONCINE_ROOT . '/templates/_film_promote_wishlist_form.php';
                        ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($showTmdbEnrich)): ?>
                    <?php require MONCINE_ROOT . '/templates/_enrich_film_panel.php'; ?>
                <?php endif; ?>

                <div class="result-actions result-actions--with-delete">
                    <a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-ghost">Retour à la liste</a>
                    <?php
                    $deleteTitle = $isWishlist ? 'Retirer des envies' : 'Supprimer de mes films';
                    $deleteConfirm = $isWishlist
                        ? 'Retirer « ' . (string) ($film['titre'] ?? '') . ' » de vos envies ?'
                        : 'Supprimer définitivement « ' . (string) ($film['titre'] ?? '') . ' » ?\n\nL’historique des visions sera aussi effacé.';
                    ?>
                    <form method="post" action="/supprimer-film.php" class="inline-form game-detail__delete-form"
                          onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="film_id" value="<?= $filmId ?>">
                        <?php if (isset($filmListContext)): ?>
                            <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
                        <?php endif; ?>
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
