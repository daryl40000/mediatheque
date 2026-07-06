<?php
/**
 * Boutons d’action rapide fiche film : ressenti, modifier, marquer vu.
 *
 * @var array<string, mixed> $film
 * @var int $filmId
 * @var int|null $monRessenti
 * @var string $popoverOpen note|edit|vu
 * @var string $saveError
 * @var bool $canManageCatalog
 * @var bool $everSeen
 * @var list<array<string, mixed>> $viewings
 */
$filmId = (int) ($filmId ?? 0);
$monRessenti = $monRessenti ?? null;
$popoverOpen = (string) ($popoverOpen ?? '');
$saveError = (string) ($saveError ?? '');
$everSeen = $everSeen ?? false;
$viewings = $viewings ?? [];
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
                aria-controls="film-popover-note">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="film-popover-note" data-detail-popover="note" role="dialog"
             aria-label="<?= Moncine\View::escape($noteTitle) ?>" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title"><?= Moncine\View::escape($noteTitle) ?></p>
                <?php
                $defaultNote = $defaultNote;
                require MONCINE_ROOT . '/templates/_marquer_film_ressenti_form.php';
                ?>
            </div>
        </div>
    </div>

    <div class="game-action-popover-anchor" data-detail-action-anchor="edit">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="edit"
                title="Modifier mon exemplaire" aria-label="Modifier mon exemplaire"
                aria-expanded="<?= $popoverOpen === 'edit' ? 'true' : 'false' ?>"
                aria-controls="film-popover-edit">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="film-popover-edit" data-detail-popover="edit" role="dialog"
             aria-label="Modifier mon exemplaire" hidden>
            <div class="game-action-popover__panel game-action-popover__panel--wide">
                <p class="game-action-popover__title">Modifier mon exemplaire</p>
                <?php
                $embedInPopover = true;
                require MONCINE_ROOT . '/templates/_film_edit_form.php';
                ?>
            </div>
        </div>
    </div>

    <div class="game-action-popover-anchor" data-detail-action-anchor="vu">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="vu"
                title="Marquer comme vu" aria-label="Marquer comme vu"
                aria-expanded="<?= $popoverOpen === 'vu' ? 'true' : 'false' ?>"
                aria-controls="film-popover-vu">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="film-popover-vu" data-detail-popover="vu" role="dialog"
             aria-label="Marquer comme vu" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title">Marquer comme vu</p>
                <?php if ($viewings !== []): ?>
                    <details class="game-action-popover__history">
                        <summary>Historique des visions (<?= count($viewings) ?>)</summary>
                        <ul class="viewings-list">
                            <?php foreach ($viewings as $view):
                                $viewId = (int) ($view['id'] ?? 0);
                                $vDate = Moncine\HistoriqueRepository::formatDateVue((string) ($view['date_vue'] ?? ''));
                                ?>
                                <li class="viewings-list__item">
                                    <span class="viewings-list__info">
                                        <?= Moncine\View::escape($vDate) ?>
                                        <?php if (isset($view['note']) && (int) $view['note'] >= 1): ?>
                                            <?php
                                            $score = (int) $view['note'];
                                            $showLabel = false;
                                            $size = 'small';
                                            require MONCINE_ROOT . '/templates/_ressenti_badge.php';
                                            ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($viewId > 0): ?>
                                        <form method="post" action="/supprimer-vision.php" class="inline-form viewings-list__delete"
                                              onsubmit="return confirm('Supprimer la vision du <?= Moncine\View::escape($vDate) ?> ?');">
                                            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                            <input type="hidden" name="film_id" value="<?= $filmId ?>">
                                            <input type="hidden" name="historique_id" value="<?= $viewId ?>">
                                            <button type="submit" class="btn btn-icon btn-danger-text btn-sm"
                                                    title="Supprimer cette vision"
                                                    aria-label="Supprimer la vision du <?= Moncine\View::escape($vDate) ?>">
                                                <svg class="icon-trash" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <?php
                $return = 'film';
                $submitLabel = $everSeen ? 'Ajouter cette date' : 'Marquer comme vu';
                require MONCINE_ROOT . '/templates/_marquer_vu_form.php';
                ?>
            </div>
        </div>
    </div>
</div>
