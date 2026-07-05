<?php
/**
 * Colonne gauche de la fiche jeu : jaquette, statut, supports, temps Steam.
 *
 * @var array<string, mixed> $game
 * @var int $completionCount
 * @var list<array<string, mixed>> $gameCompletions
 */
$completionCount = (int) ($completionCount ?? 0);
$gameCompletions = $gameCompletions ?? [];
$posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
$iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
$supplementalText = Moncine\GameEditionIcons::supplementalText($game);
$steamLabel = trim((string) ($game['steam_playtime_label'] ?? ''));
$showSteamPlaytime = Moncine\GameSteamStatsRepository::isAvailable()
    && $steamLabel !== ''
    && $steamLabel !== '—';
?>
<aside class="game-detail-sidebar" aria-label="Jaquette et infos rapides">
    <?php if ($posterSrc !== ''): ?>
        <img class="film-poster film-poster--large game-detail-sidebar__poster" src="<?= $posterSrc ?>"
             alt="Jaquette de <?= Moncine\View::escape((string) ($game['display_titre'] ?? $game['titre'] ?? '')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php if ($completionCount > 0): ?>
        <p class="game-detail-sidebar__finished">
            <span class="game-detail-sidebar__badge">Terminé</span>
            <?php if (!empty($gameCompletions[0]['completed_at'])): ?>
                <span class="game-detail-sidebar__finished-date">
                    <?= Moncine\View::escape(
                        Moncine\HistoriqueRepository::formatDateVue((string) $gameCompletions[0]['completed_at'])
                    ) ?>
                </span>
            <?php endif; ?>
            <?php if ($completionCount > 1): ?>
                <span class="hint game-detail-sidebar__finished-count">
                    ×<?= $completionCount ?>
                </span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($iconKeys !== [] || $supplementalText !== ''): ?>
        <div class="game-detail-sidebar__editions">
            <?php require MONCINE_ROOT . '/templates/_game_edition_icons.php'; ?>
        </div>
    <?php endif; ?>

    <?php if ($showSteamPlaytime): ?>
        <p class="game-detail-sidebar__playtime<?= !empty($game['steam_never_played']) ? ' text-muted' : '' ?>">
            <span class="game-detail-sidebar__playtime-label">Temps Steam</span>
            <strong><?= Moncine\View::escape($steamLabel) ?></strong>
        </p>
    <?php endif; ?>
</aside>
