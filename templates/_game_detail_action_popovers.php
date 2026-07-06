<?php
/**
 * Boutons d’action rapide et bulles associées (notation, temps, exemplaire, terminé).
 *
 * @var array<string, mixed> $game
 * @var int $gameId
 * @var int|null $monRessenti
 * @var string $popoverOpen note|playtime|edit|finish
 * @var string $saveError
 * @var bool $canManageCatalog
 * @var array<string, string> $platformChoices
 * @var list<array<string, mixed>> $gameCompletions
 */
$gameId = (int) ($gameId ?? 0);
$game = $game ?? [];
$monRessenti = $monRessenti ?? null;
$popoverOpen = (string) ($popoverOpen ?? '');
$saveError = (string) ($saveError ?? '');
$canManageCatalog = $canManageCatalog ?? false;
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$gameCompletions = $gameCompletions ?? [];
$gameRow = $game;
$oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
$catalogPlatformKeys = $game['platform_list'] ?? Moncine\GamePlatformList::catalogKeysFromRow($game);
$catalogPlatformKeysAttr = Moncine\View::escape(implode(',', $catalogPlatformKeys));
$showPlaytimeAction = Moncine\GameSchema::hasManualPlaytimeColumn();
$showFinishAction = Moncine\GameCompletionRepository::isAvailable();
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
                aria-controls="game-popover-note">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="game-popover-note" data-detail-popover="note" role="dialog"
             aria-label="<?= Moncine\View::escape($noteTitle) ?>" hidden>
            <div class="game-action-popover__panel">
                <p class="game-action-popover__title"><?= Moncine\View::escape($noteTitle) ?></p>
                <?php require MONCINE_ROOT . '/templates/_marquer_joue_form.php'; ?>
            </div>
        </div>
    </div>

    <?php if ($showPlaytimeAction): ?>
        <div class="game-action-popover-anchor" data-detail-action-anchor="playtime">
            <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="playtime"
                    title="Temps de jeu" aria-label="Temps de jeu"
                    aria-expanded="<?= $popoverOpen === 'playtime' ? 'true' : 'false' ?>"
                    aria-controls="game-popover-playtime">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M15 1H9v2h6V1zm-3 7a1 1 0 0 1 1 1v3.59l2.3 2.3-1.41 1.41-2.89-2.89V9a1 1 0 0 1 1-1zm0-6C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                </svg>
            </button>
            <div class="game-action-popover" id="game-popover-playtime" data-detail-popover="playtime" role="dialog"
                 aria-label="Temps de jeu" hidden>
                <div class="game-action-popover__panel">
                    <p class="game-action-popover__title">Temps de jeu</p>
                    <?php if ($saveError !== '' && $popoverOpen === 'playtime'): ?>
                        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
                    <?php endif; ?>
                    <form method="post" action="/modifier-jeu-exemplaire.php" class="film-edit-form import-form">
                        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                        <input type="hidden" name="game_id" value="<?= $gameId ?>">
                        <input type="hidden" name="scope" value="playtime">
                        <?php require MONCINE_ROOT . '/templates/_game_manual_playtime_fields.php'; ?>
                        <button type="submit" class="btn btn-primary">Enregistrer le temps</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="game-action-popover-anchor" data-detail-action-anchor="edit">
        <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="edit"
                title="Modifier mon exemplaire" aria-label="Modifier mon exemplaire"
                aria-expanded="<?= $popoverOpen === 'edit' ? 'true' : 'false' ?>"
                aria-controls="game-popover-edit">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
            </svg>
        </button>
        <div class="game-action-popover" id="game-popover-edit" data-detail-popover="edit" role="dialog"
             aria-label="Modifier mon exemplaire" hidden>
            <div class="game-action-popover__panel game-action-popover__panel--wide">
                <p class="game-action-popover__title">Modifier mon exemplaire</p>
                <p class="hint">
                    Plateformes, supports physiques, version dématérialisée (Steam, Battle.net…).
                    Le titre et la jaquette catalogue ne se modifient pas ici.
                </p>
                <?php if ($canManageCatalog && $oeuvreId > 0): ?>
                    <p class="hint">
                        <a href="<?= Moncine\View::escape(Moncine\View::oeuvreJeuUrl($oeuvreId)) ?>">
                            Modifier la fiche catalogue (admin)
                        </a>
                    </p>
                <?php elseif (!$canManageCatalog): ?>
                    <p class="hint">
                        Pour corriger le titre ou la jaquette, contactez l’administrateur du site.
                    </p>
                <?php endif; ?>
                <?php if ($saveError !== '' && $popoverOpen === 'edit'): ?>
                    <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
                <?php endif; ?>
                <form method="post" action="/modifier-jeu-exemplaire.php" class="film-edit-form import-form"
                      data-game-library-edit-form="1"
                      data-catalog-platform-keys="<?= $catalogPlatformKeysAttr ?>"
                      data-can-manage-catalog="0">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="game_id" value="<?= $gameId ?>">
                    <?php
                    $libraryEditOnly = true;
                    $showManualPlaytimeFields = false;
                    require MONCINE_ROOT . '/templates/_game_form_fields.php';
                    ?>
                    <button type="submit" class="btn btn-primary">Enregistrer mon exemplaire</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($showFinishAction): ?>
        <div class="game-action-popover-anchor" data-detail-action-anchor="finish">
            <button type="button" class="btn btn-icon btn-secondary btn-sm" data-detail-action="finish"
                    title="Marquer terminé" aria-label="Marquer terminé"
                    aria-expanded="<?= $popoverOpen === 'finish' ? 'true' : 'false' ?>"
                    aria-controls="game-popover-finish">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </button>
            <div class="game-action-popover" id="game-popover-finish" data-detail-popover="finish" role="dialog"
                 aria-label="Marquer comme terminé" hidden>
                <div class="game-action-popover__panel">
                    <p class="game-action-popover__title">Marquer comme terminé</p>
                    <?php if ($gameCompletions !== []): ?>
                        <details class="game-action-popover__history">
                            <summary>Historique des fins (<?= count($gameCompletions) ?>)</summary>
                            <ul class="viewings-list">
                                <?php foreach ($gameCompletions as $completion):
                                    $cDate = Moncine\HistoriqueRepository::formatDateVue((string) ($completion['completed_at'] ?? ''));
                                    ?>
                                    <li class="viewings-list__item">
                                        <span class="viewings-list__info"><?= Moncine\View::escape($cDate) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                    <?php require MONCINE_ROOT . '/templates/_marquer_jeu_fini_form.php'; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
