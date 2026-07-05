<?php
/**
 * Mes jeux — vue vignettes (grille de jaquettes).
 *
 * @var list<array<string, mixed>> $games
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $viewMode
 * @var Moncine\GameListFilter $listFilter
 */
$listFilter = $listFilter ?? Moncine\GameListFilter::empty();
$showBulkSelect = isset($canAssignFranchise) ? (bool) $canAssignFranchise : Moncine\GameFranchiseRepository::isAvailable();
$gridSortLink = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode, $listFilter): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(Moncine\View::gamesSortUrl($column, $sortBy, $sortDir, $query, $viewMode, $listFilter)) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<div class="collection-grid-bar collection-grid-bar--games">
    <?php if ($showBulkSelect): ?>
        <label class="collection-grid-bar__select-all collection-select-all">
            <input type="checkbox" id="collection-select-all" aria-label="Tout sélectionner">
            <span>Tout sélectionner</span>
        </label>
    <?php endif; ?>
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $gridSortLink('Titre', 'titre'); ?>
        <?php $gridSortLink('Année', 'annee'); ?>
        <?php if (Moncine\GameFranchiseRepository::isAvailable()): ?>
        <?php $gridSortLink('Saga', 'franchise'); ?>
        <?php endif; ?>
        <?php $gridSortLink('Plateforme', 'platform'); ?>
        <?php $gridSortLink('Note', 'note'); ?>
        <?php $gridSortLink('Studio', 'studio'); ?>
        <?php $gridSortLink('Fini le', 'finished_at'); ?>
        <?php if (Moncine\GameSteamStatsRepository::isAvailable()): ?>
        <?php $gridSortLink('Temps Steam', 'steam_playtime'); ?>
        <?php endif; ?>
    </nav>
</div>

<ul class="collection-grid collection-grid--games" role="list">
    <?php foreach ($games as $game):
        $bibId = (int) ($game['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $gameUrl = Moncine\View::gameUrl($bibId);
        $annee = (int) ($game['annee'] ?? 0);
        $platformShort = (string) ($game['platform_short'] ?? '');
        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <?php if ($showBulkSelect): ?>
                    <label class="collection-grid__check" title="Sélectionner">
                        <input type="checkbox" name="game_ids[]"
                               value="<?= $bibId ?>"
                               class="collection-film-cb"
                               aria-label="Sélectionner <?= Moncine\View::escape($displayTitle) ?>">
                    </label>
                <?php endif; ?>
                <a href="<?= Moncine\View::escape($gameUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Jaquette de <?= Moncine\View::escape($displayTitle) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="collection-grid__caption">
                        <h3 class="collection-grid__title collection-grid__title--game">
                            <span><?= Moncine\View::escape($displayTitle) ?></span>
                            <?php if ((string) ($game['linux_badge'] ?? '') !== '' || !empty($game['tested_on_linux']) || !empty($game['linux_not_supported'])): ?>
                                <span class="collection-grid__linux-badge">
                                    <?php
                                    $size = 'sm';
                                    $plain = true;
                                    require MONCINE_ROOT . '/templates/_game_linux_badge_if_set.php';
                                    ?>
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p class="collection-grid__meta">
                            <?php if ($platformShort !== ''): ?>
                                <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape($platformShort) ?></span>
                            <?php endif; ?>
                            <?php if ($annee > 0): ?>
                                <span class="collection-grid__year"><?= $annee ?></span>
                            <?php endif; ?>
                        </p>
                        <?php
                        $iconKeys = $game['edition_icon_keys'] ?? Moncine\GameEditionIcons::iconKeys($game);
                        $supplementalText = Moncine\GameEditionIcons::supplementalText($game);
                        if ($iconKeys !== [] || $supplementalText !== ''):
                            ?>
                            <p class="collection-grid__meta collection-grid__meta--game-editions">
                                <?php require MONCINE_ROOT . '/templates/_game_edition_icons.php'; ?>
                            </p>
                        <?php endif; ?>
                        <div class="collection-grid__ratings">
                            <?php
                            $film = $game;
                            $showFoyerAverage = true;
                            $layout = 'stacked';
                            ob_start();
                            require MONCINE_ROOT . '/templates/_film_ratings.php';
                            $ratingsHtml = trim((string) ob_get_clean());
                            if ($ratingsHtml !== '' && $ratingsHtml !== '—') {
                                echo $ratingsHtml;
                            }
                            ?>
                        </div>
                    </div>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
