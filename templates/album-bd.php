<?php
/** @var array<string, mixed>|null $album */
/** @var bool $saved */
/** @var int $albumId */
/** @var bool $isWishlist */
/** @var string $listBackUrl */
/** @var int|null $monRessenti */
/** @var list<array<string, mixed>> $readHistory */
/** @var bool $everRead */
/** @var string $popoverOpen */

$albumId = (int) ($albumId ?? 0);
$isWishlist = $isWishlist ?? false;
$listBackUrl = $listBackUrl ?? '/bd.php';
$readHistory = $readHistory ?? [];
$everRead = (bool) ($everRead ?? false);
$popoverOpen = (string) ($popoverOpen ?? '');
?>
<section class="collection-page game-detail-page">
    <?php if ($album === null): ?>
        <h1>Album introuvable</h1>
        <p class="hint">Cet album n’existe pas ou n’est pas accessible dans votre bibliothèque.</p>
        <p><a href="/bd.php" class="btn btn-secondary">← Mes BD</a></p>
    <?php else: ?>
        <?php
        $posterSrc = Moncine\View::posterSrc($album['poster_url'] ?? null);
        $seriesId = (int) ($album['series_id'] ?? 0);
        $seriesTitre = trim((string) ($album['series_titre'] ?? ''));
        $seriesUrl = $seriesId > 0
            ? Moncine\View::bdSeriesUrl($seriesId, 'tome', 'asc', [
                'statut' => $isWishlist ? Moncine\LibraryStatut::WISHLIST : Moncine\LibraryStatut::COLLECTION,
            ])
            : '';
        $albumTitle = trim((string) ($album['titre'] ?? ''));
        $h1Title = $albumTitle !== '' ? $albumTitle : (string) ($album['display_titre'] ?? 'Album');
        $tomeNumero = (int) ($album['tome_numero'] ?? 0);
        $tomeLabel = trim((string) ($album['tome_label'] ?? ''));
        $numeroLabel = Moncine\BdRowMapper::tomeNumeroLabel($tomeNumero, $tomeLabel);
        $isPossessed = !empty($album['is_possessed']);
        $readAtLabel = (string) ($album['read_at_label'] ?? '');
        ?>

        <p><a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-secondary btn-sm">← Retour à la série</a></p>

        <?php if ($saved): ?>
            <div class="alert alert-success">Tome enregistré.</div>
        <?php endif; ?>
        <?php if (isset($_GET['promoted']) && (string) $_GET['promoted'] === '1'): ?>
            <div class="alert alert-success">Album ajouté à votre collection.</div>
        <?php endif; ?>
        <?php if (isset($_GET['lu']) && (string) $_GET['lu'] === '1'): ?>
            <div class="alert alert-success">
                Lecture enregistrée<?php if (!empty($_GET['lu_date'])): ?>
                    (<?= Moncine\View::escape((string) $_GET['lu_date']) ?>)
                <?php endif; ?>.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['lu_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['lu_error']) ?></p>
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
        <?php if (!empty($_GET['wishlist_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['wishlist_error']) ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['wishlist']) && (string) $_GET['wishlist'] === '1'): ?>
            <div class="alert alert-success">Tome ajouté à vos envies.</div>
        <?php endif; ?>
        <?php if (($editError ?? '') !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($editError) ?></p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce tome est dans vos envies (pas encore dans votre collection).</p>
        <?php endif; ?>

        <article class="film-detail game-detail film-detail--with-poster">
            <?php require MONCINE_ROOT . '/templates/_bd_detail_sidebar.php'; ?>

            <div class="film-detail__body game-detail__body">
                <header class="film-detail__heading game-detail__heading">
                    <h1 class="game-detail__title-row">
                        <span><?= Moncine\View::escape($h1Title) ?></span>
                        <?php if ((int) ($album['annee'] ?? 0) > 0): ?>
                            <span class="film-year">(<?= (int) $album['annee'] ?>)</span>
                        <?php endif; ?>
                        <?php if (!$isWishlist || !empty($monRessenti)): ?>
                            <?php require MONCINE_ROOT . '/templates/_game_detail_ressenti_title.php'; ?>
                        <?php endif; ?>
                    </h1>
                    <p class="game-detail__saga">
                        <?php if ($seriesUrl !== '' && $seriesTitre !== ''): ?>
                            <span class="game-detail__saga-label">Série</span>
                            <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="saga-link"><?= Moncine\View::escape($seriesTitre) ?></a>
                        <?php elseif ($seriesTitre !== ''): ?>
                            <span class="game-detail__saga-label">Série</span>
                            <?= Moncine\View::escape($seriesTitre) ?>
                        <?php endif; ?>
                        <?php if ($numeroLabel !== ''): ?>
                            <?= $seriesTitre !== '' ? ' · ' : '' ?>
                            <?php if (!empty($album['est_hors_serie'])): ?>
                                <span class="badge">HS</span>
                            <?php endif; ?>
                            Tome <?= Moncine\View::escape($numeroLabel) ?>
                        <?php endif; ?>
                        <?php if ((string) ($album['kind_label'] ?? '') !== ''): ?>
                            · <?= Moncine\View::escape((string) $album['kind_label']) ?>
                        <?php endif; ?>
                    </p>
                </header>

                <section class="game-detail__facts" aria-labelledby="bd-facts-heading">
                    <h2 id="bd-facts-heading" class="game-detail__section-title">Détails</h2>
                    <?php require MONCINE_ROOT . '/templates/_bd_detail_facts_columns.php'; ?>
                </section>

                <?php if (trim((string) ($album['synopsis'] ?? '')) !== ''): ?>
                    <section class="game-detail__synopsis-section" aria-labelledby="bd-synopsis-heading">
                        <h2 id="bd-synopsis-heading" class="game-detail__section-title">Résumé</h2>
                        <p class="game-detail__synopsis"><?= nl2br(Moncine\View::escape((string) $album['synopsis'])) ?></p>
                    </section>
                <?php endif; ?>

                <?php if (!empty($bdSeriesNeighbors)): ?>
                    <?php require MONCINE_ROOT . '/templates/_bd_series_context_strip.php'; ?>
                <?php endif; ?>

                <?php if ($isWishlist): ?>
                    <section class="film-promote-panel">
                        <h2 class="film-promote-panel__title">Ajouter à ma collection</h2>
                        <p class="hint">Vous avez acheté ce tome ? Il passera dans votre collection.</p>
                        <form method="post" action="/promouvoir-bd-collection.php" class="inline-form">
                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                            <input type="hidden" name="album_id" value="<?= $albumId ?>">
                            <button type="submit" class="btn btn-primary">J’ai acheté cet album</button>
                        </form>
                    </section>
                <?php endif; ?>

                <div class="result-actions result-actions--with-delete">
                    <a href="<?= Moncine\View::escape($listBackUrl) ?>" class="btn btn-ghost">Retour à la série</a>
                    <?php
                    $deleteTitle = $isWishlist ? 'Retirer des envies' : 'Supprimer de mes BD';
                    $deleteConfirm = $isWishlist
                        ? 'Retirer « ' . $h1Title . ' » de vos envies ?'
                        : 'Supprimer « ' . $h1Title . ' » de vos BD ?';
                    ?>
                    <form method="post" action="/supprimer-bd.php" class="inline-form game-detail__delete-form"
                          onsubmit="return confirm(<?= json_encode($deleteConfirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="album_id" value="<?= $albumId ?>">
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
