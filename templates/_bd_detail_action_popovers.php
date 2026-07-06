<?php
/**
 * Boutons d’action rapide fiche BD : ressenti, modifier, marquer lu.
 *
 * @var array<string, mixed> $album
 * @var int $albumId
 * @var int|null $monRessenti
 * @var string $popoverOpen note|edit|lu
 * @var string $editError
 * @var bool $everRead
 * @var list<array<string, mixed>> $readHistory
 * @var array<string, string> $supportChoices
 */
$albumId = (int) ($albumId ?? 0);
$album = $album ?? [];
$monRessenti = $monRessenti ?? null;
$popoverOpen = (string) ($popoverOpen ?? '');
$editError = (string) ($editError ?? '');
$everRead = $everRead ?? false;
$readHistory = $readHistory ?? [];
$seriesId = (int) ($album['series_id'] ?? 0);
$seriesTitre = trim((string) ($album['series_titre'] ?? ''));
$isPossessed = !empty($album['is_possessed']);
$inWishlist = !empty($inWishlist);
$defaultNote = isset($monRessenti) ? Moncine\RessentiNote::normalizeScore((int) $monRessenti) : null;
$noteTitle = $defaultNote !== null ? 'Modifier mon ressenti' : 'Mon ressenti';
?>
<div class="game-detail-sidebar__actions"
     data-detail-actions
     <?= $popoverOpen !== '' ? ' data-popover-open="' . Moncine\View::escape($popoverOpen) . '"' : '' ?>>
    <div class="game-action-popover-anchor" data-detail-action-anchor="note">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="note"
                title="<?= Moncine\View::escape($noteTitle) ?>" aria-label="<?= Moncine\View::escape($noteTitle) ?>"
                aria-expanded="<?= $popoverOpen === 'note' ? 'true' : 'false' ?>"
                aria-controls="bd-popover-note">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="bd-popover-note" data-detail-popover="note" role="dialog"
             aria-label="<?= Moncine\View::escape($noteTitle) ?>" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title"><?= Moncine\View::escape($noteTitle) ?></p>
                <?php require MONCINE_ROOT . '/templates/_marquer_bd_ressenti_form.php'; ?>
            </div>
        </div>
    </div>

    <div class="game-action-popover-anchor" data-detail-action-anchor="edit">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="edit"
                title="Modifier ce tome" aria-label="Modifier ce tome"
                aria-expanded="<?= $popoverOpen === 'edit' ? 'true' : 'false' ?>"
                aria-controls="bd-popover-edit">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="bd-popover-edit" data-detail-popover="edit" role="dialog"
             aria-label="Modifier ce tome" hidden>
            <div class="game-action-popover__panel game-action-popover__panel--wide">
                <p class="game-action-popover__title">Modifier ce tome</p>
                <?php if ($editError !== '' && $popoverOpen === 'edit'): ?>
                    <div class="alert alert-warning"><?= Moncine\View::escape($editError) ?></div>
                <?php endif; ?>
                <form method="post" action="/traiter-tome-bd.php" enctype="multipart/form-data" class="import-form film-edit-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="album_id" value="<?= $albumId ?>">
                    <?php
                    $series = ['id' => $seriesId, 'titre' => $seriesTitre];
                    $showPossessionHint = false;
                    require MONCINE_ROOT . '/templates/_bd_form_fields.php';
                    $coverInputId = 'popover_bd_cover';
                    $posterUrlInputId = 'popover_bd_poster_url';
                    require MONCINE_ROOT . '/templates/_bd_cover_fields.php';
                    ?>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isPossessed): ?>
    <div class="game-action-popover-anchor" data-detail-action-anchor="lu">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="lu"
                title="Marquer comme lu" aria-label="Marquer comme lu"
                aria-expanded="<?= $popoverOpen === 'lu' ? 'true' : 'false' ?>"
                aria-controls="bd-popover-lu">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="bd-popover-lu" data-detail-popover="lu" role="dialog"
             aria-label="Marquer comme lu" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title">Marquer comme lu</p>
                <?php if ($readHistory !== []): ?>
                    <details class="game-action-popover__history">
                        <summary>Historique de lecture (<?= count($readHistory) ?>)</summary>
                        <ul class="viewings-list">
                            <?php foreach ($readHistory as $view):
                                $viewDate = Moncine\HistoriqueRepository::formatDateVue((string) ($view['date_vue'] ?? ''));
                                ?>
                                <li class="viewings-list__item">
                                    <span class="viewings-list__info">
                                        <?= Moncine\View::escape($viewDate) ?>
                                        <?php if (isset($view['note']) && (int) $view['note'] >= 1): ?>
                                            <?php
                                            $score = (int) $view['note'];
                                            $showLabel = false;
                                            $size = 'small';
                                            require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                            ?>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <?php
                $return = 'album';
                $submitLabel = $everRead ? 'Ajouter cette date' : 'Marquer comme lu';
                require MONCINE_ROOT . '/templates/_marquer_lu_form.php';
                ?>
            </div>
        </div>
    </div>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_bd_wishlist_action.php'; ?>
    <?php endif; ?>
</div>
