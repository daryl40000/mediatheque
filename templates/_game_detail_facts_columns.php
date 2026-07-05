<?php
/**
 * Détails jeu en deux colonnes (fiche bibliothèque).
 *
 * @var array<string, mixed> $game
 * @var list<string> $genreList
 */
$game = $game ?? [];
$genreList = $genreList ?? [];
$platformDisplayKeys = $game['owned_platform_list']
    ?? Moncine\GamePlatformList::ownedKeysFromRow(is_array($game) ? $game : []);
if ($platformDisplayKeys === []) {
    $platformDisplayKeys = $game['platform_list']
        ?? Moncine\GamePlatformList::catalogKeysFromRow(is_array($game) ? $game : []);
}
$gameModeList = $game['game_mode_list'] ?? Moncine\GameGenre::parseList((string) ($game['game_mode'] ?? ''));
$themeList = $game['theme_list'] ?? Moncine\GameGenre::parseList((string) ($game['theme'] ?? ''));
?>
<div class="game-detail-facts-grid">
    <dl class="film-facts film-facts--game game-detail-facts-grid__col">
        <?php if ((string) ($game['studio'] ?? '') !== ''): ?>
            <dt>Studio</dt>
            <dd><?= Moncine\View::escape((string) $game['studio']) ?></dd>
        <?php endif; ?>
        <?php if ((string) ($game['editeur'] ?? '') !== ''): ?>
            <dt>Éditeur</dt>
            <dd><?= Moncine\View::escape((string) $game['editeur']) ?></dd>
        <?php endif; ?>
        <?php if ($platformDisplayKeys !== []): ?>
            <dt>Plateforme<?= count($platformDisplayKeys) > 1 ? 's' : '' ?></dt>
            <dd class="game-detail__platform-row game-genre-tags">
                <?php foreach ($platformDisplayKeys as $platformKey): ?>
                    <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape(Moncine\GamePlatform::shortLabel((string) $platformKey)) ?></span>
                <?php endforeach; ?>
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
    </dl>

    <dl class="film-facts film-facts--game game-detail-facts-grid__col">
        <?php if ($gameModeList !== []): ?>
            <dt>Mode de jeu</dt>
            <dd class="game-genre-tags">
                <?php foreach ($gameModeList as $modeTag): ?>
                    <span class="magazine-tag magazine-tag--game-mode"><?= Moncine\View::escape((string) $modeTag) ?></span>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
        <?php if ($themeList !== []): ?>
            <dt>Thème</dt>
            <dd class="game-genre-tags">
                <?php foreach ($themeList as $themeTag): ?>
                    <span class="magazine-tag magazine-tag--game-theme"><?= Moncine\View::escape((string) $themeTag) ?></span>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
    </dl>
</div>
