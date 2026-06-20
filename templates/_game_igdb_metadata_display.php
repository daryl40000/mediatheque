<?php
/**
 * Affichage métadonnées IGDB (franchise, modes, thèmes, acronymes).
 *
 * @var array<string, mixed> $game
 */
$game = $game ?? [];
$gameModeList = $game['game_mode_list'] ?? Moncine\GameGenre::parseList((string) ($game['game_mode'] ?? ''));
$themeList = $game['theme_list'] ?? Moncine\GameGenre::parseList((string) ($game['theme'] ?? ''));
$acronymList = $game['alternative_name_list'] ?? Moncine\GameGenre::parseList((string) ($game['alternative_names'] ?? ''));
$franchise = trim((string) ($game['franchise'] ?? ''));
?>
<?php if ($franchise !== ''): ?>
    <dt>Saga</dt>
    <dd>
        <?php
        $franchiseName = $franchise;
        require MONCINE_ROOT . '/templates/_game_franchise_link.php';
        ?>
    </dd>
<?php endif; ?>

<?php if ($gameModeList !== []): ?>
    <dt>Modes de jeu</dt>
    <dd class="game-genre-tags">
        <?php foreach ($gameModeList as $modeTag): ?>
            <span class="magazine-tag magazine-tag--game-mode"><?= Moncine\View::escape((string) $modeTag) ?></span>
        <?php endforeach; ?>
    </dd>
<?php endif; ?>

<?php if ($themeList !== []): ?>
    <dt>Thèmes</dt>
    <dd class="game-genre-tags">
        <?php foreach ($themeList as $themeTag): ?>
            <span class="magazine-tag magazine-tag--game-theme"><?= Moncine\View::escape((string) $themeTag) ?></span>
        <?php endforeach; ?>
    </dd>
<?php endif; ?>

<?php if ($acronymList !== []): ?>
    <dt>Acronymes</dt>
    <dd class="game-genre-tags">
        <?php foreach ($acronymList as $acronym): ?>
            <span class="magazine-tag magazine-tag--game-acronym"><?= Moncine\View::escape((string) $acronym) ?></span>
        <?php endforeach; ?>
    </dd>
<?php endif; ?>
