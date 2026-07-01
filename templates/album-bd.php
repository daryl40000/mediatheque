<?php
/** @var array<string, mixed>|null $album */
/** @var bool $saved */
/** @var int $albumId */
/** @var bool $isWishlist */
/** @var string $listBackUrl */
/** @var int|null $noteSur10 */
/** @var float|null $noteFoyerMoyenne */
/** @var list<array<string, mixed>> $readHistory */
/** @var bool $everRead */
/** @var array<string, string> $supportChoices */
/** @var string $editError */

$albumId = (int) ($albumId ?? 0);
$isWishlist = $isWishlist ?? false;
$listBackUrl = $listBackUrl ?? '/bd.php';
?>
<section class="collection-page">
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
        $numeroLabel = $tomeNumero > 0 ? (string) $tomeNumero : $tomeLabel;
        $isPossessed = !empty($album['is_possessed']);
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
        <?php if (!empty($_GET['delete_error'])): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape((string) $_GET['delete_error']) ?></p>
        <?php endif; ?>
        <?php if (($editError ?? '') !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($editError) ?></p>
        <?php endif; ?>

        <?php if ($isWishlist): ?>
            <p class="hint film-wishlist-badge">Ce tome est dans vos envies (pas encore dans votre collection).</p>
        <?php endif; ?>

        <article class="film-detail film-detail--with-poster">
            <?php if ($posterSrc !== ''): ?>
                <img class="film-poster film-poster--large film-poster--bd" src="<?= $posterSrc ?>"
                     alt="Couverture de <?= Moncine\View::escape($h1Title) ?>">
            <?php else: ?>
                <span class="film-poster film-poster--large film-poster--bd film-poster--empty" aria-hidden="true"></span>
            <?php endif; ?>

            <div class="film-detail__body">
                <header class="film-detail__heading">
                    <h1><?= Moncine\View::escape($h1Title) ?></h1>
                    <p class="lead">
                        <?php if ($seriesUrl !== '' && $seriesTitre !== ''): ?>
                            <a href="<?= Moncine\View::escape($seriesUrl) ?>"><?= Moncine\View::escape($seriesTitre) ?></a>
                        <?php elseif ($seriesTitre !== ''): ?>
                            <?= Moncine\View::escape($seriesTitre) ?>
                        <?php endif; ?>
                        <?php if ($numeroLabel !== ''): ?>
                            <?= $seriesTitre !== '' ? ' · ' : '' ?>Tome <?= Moncine\View::escape($numeroLabel) ?>
                        <?php endif; ?>
                        <?php if ((string) ($album['kind_label'] ?? '') !== ''): ?>
                            · <?= Moncine\View::escape((string) $album['kind_label']) ?>
                        <?php endif; ?>
                        <?php if ((int) ($album['annee'] ?? 0) > 0): ?>
                            · <?= (int) $album['annee'] ?>
                        <?php endif; ?>
                    </p>
                </header>

                <dl class="film-facts">
                    <?php if ((string) ($album['scenariste'] ?? '') !== ''): ?>
                        <dt>Scénariste</dt>
                        <dd><?= Moncine\View::escape((string) $album['scenariste']) ?></dd>
                    <?php endif; ?>
                    <?php if ((string) ($album['dessinateur'] ?? '') !== ''): ?>
                        <dt>Dessinateur</dt>
                        <dd><?= Moncine\View::escape((string) $album['dessinateur']) ?></dd>
                    <?php endif; ?>
                    <?php if ((string) ($album['editeur'] ?? '') !== ''): ?>
                        <dt>Éditeur</dt>
                        <dd><?= Moncine\View::escape((string) $album['editeur']) ?></dd>
                    <?php endif; ?>
                    <?php if ((string) ($album['genre'] ?? '') !== ''): ?>
                        <dt>Genre</dt>
                        <dd><span class="magazine-tag magazine-tag--game-genre"><?= Moncine\View::escape((string) $album['genre']) ?></span></dd>
                    <?php endif; ?>
                    <dt>Exemplaire</dt>
                    <dd>
                        <?php if ($isPossessed): ?>
                            <?= Moncine\View::escape((string) ($album['support_label'] ?? '')) ?>
                        <?php else: ?>
                            <span class="magazine-tag magazine-tag--none">Non possédé</span>
                        <?php endif; ?>
                    </dd>
                    <?php if ((string) ($album['added_at_label'] ?? '') !== ''): ?>
                        <dt><?= $isWishlist ? 'Envie ajoutée le' : 'Ajouté le' ?></dt>
                        <dd><?= Moncine\View::escape((string) $album['added_at_label']) ?></dd>
                    <?php endif; ?>
                    <?php if (!$isWishlist && (!empty($noteSur10) || ($noteFoyerMoyenne ?? null) !== null)): ?>
                        <dt>Notes</dt>
                        <dd>
                            <?php if (!empty($noteSur10)): ?>
                                <p class="film-ratings film-ratings--detail">
                                    <span class="film-ratings__label">Votre note</span>
                                    <span class="film-note" title="Votre note sur ce tome"><?= (int) $noteSur10 ?>/10</span>
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
                    <?php if ((string) ($album['read_at_label'] ?? '') !== ''): ?>
                        <dt>Dernière lecture</dt>
                        <dd><?= Moncine\View::escape((string) $album['read_at_label']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ((string) ($album['synopsis'] ?? '') !== ''): ?>
                    <section>
                        <h2>Description</h2>
                        <p><?= nl2br(Moncine\View::escape((string) $album['synopsis'])) ?></p>
                    </section>
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
                <?php else: ?>
                    <?php if ($readHistory !== []): ?>
                        <h2>Historique de lecture</h2>
                        <ul class="viewings-list">
                            <?php foreach ($readHistory as $view):
                                $viewDate = Moncine\HistoriqueRepository::formatDateVue((string) ($view['date_vue'] ?? ''));
                                ?>
                                <li class="viewings-list__item">
                                    <span class="viewings-list__info">
                                        <?= Moncine\View::escape($viewDate) ?>
                                        <?php if (isset($view['note']) && (int) $view['note'] >= 1): ?>
                                            — <?= (int) $view['note'] ?>/10
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <section class="marquer-vu-panel">
                        <h2 class="marquer-vu-panel__title">Enregistrer une lecture</h2>
                        <?php
                        $return = 'album';
                        $submitLabel = $everRead ? 'Ajouter cette date' : 'Marquer comme lu';
                        $defaultNote = !empty($noteSur10) ? (int) $noteSur10 : null;
                        require MONCINE_ROOT . '/templates/_marquer_lu_form.php';
                        ?>
                    </section>
                <?php endif; ?>

                <details class="film-edit-panel" id="modifier-tome"<?= ($saved || ($editError ?? '') !== '') ? ' open' : '' ?>>
                    <summary class="film-edit-panel__summary">Modifier ce tome</summary>
                    <form method="post" action="/traiter-tome-bd.php" enctype="multipart/form-data" class="import-form film-edit-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="album_id" value="<?= $albumId ?>">
                        <?php
                        $series = [
                            'id' => $seriesId,
                            'titre' => $seriesTitre,
                        ];
                        $showPossessionHint = false;
                        require MONCINE_ROOT . '/templates/_bd_form_fields.php';
                        ?>

                        <?php
                        $coverInputId = 'edit_bd_cover';
                        $posterUrlInputId = 'edit_bd_poster_url';
                        require MONCINE_ROOT . '/templates/_bd_cover_fields.php';
                        ?>

                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </form>
                </details>

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
