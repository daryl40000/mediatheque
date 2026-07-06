<?php
/**
 * Mes jeux — vue bibliothèque (tranches verticales, comme des dos de livres).
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
$shelfSortLink = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode, $listFilter): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(Moncine\View::gamesSortUrl($column, $sortBy, $sortDir, $query, $viewMode, $listFilter)) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
/** Nombre de tranches par étagère (défilement horizontal si dépassement). */
$shelfChunkSize = 28;
$shelves = array_chunk($games, $shelfChunkSize);
$spineHeightPx = Moncine\View::gameShelfSpineHeightPx();
?>
<div class="collection-grid-bar collection-grid-bar--games game-shelf-bar">
    <?php if ($showBulkSelect): ?>
        <label class="collection-grid-bar__select-all collection-select-all">
            <input type="checkbox" id="collection-select-all" aria-label="Tout sélectionner">
            <span>Tout sélectionner</span>
        </label>
    <?php endif; ?>
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $shelfSortLink('Titre', 'titre'); ?>
        <?php $shelfSortLink('Année', 'annee'); ?>
        <?php if (Moncine\GameFranchiseRepository::isAvailable()): ?>
        <?php $shelfSortLink('Saga', 'franchise'); ?>
        <?php endif; ?>
        <?php $shelfSortLink('Plateforme', 'platform'); ?>
        <?php $shelfSortLink('Note', 'note'); ?>
        <?php $shelfSortLink('Studio', 'studio'); ?>
        <?php $shelfSortLink('Fini le', 'finished_at'); ?>
        <?php if (Moncine\GamePlaytime::isAvailable()): ?>
        <?php $shelfSortLink('Temps de jeu', 'steam_playtime'); ?>
        <?php endif; ?>
    </nav>
</div>

<div class="game-shelf-library" role="list" aria-label="Collection en vue bibliothèque"
     style="--spine-h: <?= (int) $spineHeightPx ?>">
    <?php foreach ($shelves as $shelfGames): ?>
        <section class="game-shelf" aria-label="Étagère">
            <ul class="game-shelf__spines" role="list">
                <?php foreach ($shelfGames as $game):
                    $bibId = (int) ($game['id'] ?? 0);
                    $gameUrl = Moncine\View::gameUrl($bibId);
                    $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
                    $platformShort = (string) ($game['platform_short'] ?? '');
                    $annee = (int) ($game['annee'] ?? 0);
                    $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
                    $hasPoster = $posterSrc !== '';
                    $spineHueStyle = Moncine\View::gameSpineHueStyle($game);
                    ?>
                    <li class="game-shelf__spine" role="listitem">
                        <article class="game-shelf__card">
                            <?php if ($showBulkSelect): ?>
                                <label class="game-shelf__check" title="Sélectionner">
                                    <input type="checkbox" name="game_ids[]"
                                           value="<?= $bibId ?>"
                                           class="collection-film-cb"
                                           aria-label="Sélectionner <?= Moncine\View::escape($displayTitle) ?>">
                                </label>
                            <?php endif; ?>
                            <a href="<?= Moncine\View::escape($gameUrl) ?>"
                               class="game-shelf__link<?= $hasPoster ? ' game-shelf__link--poster' : ' game-shelf__link--fallback' ?>"
                               <?php if (!$hasPoster): ?>style="<?= Moncine\View::escape($spineHueStyle) ?>"<?php endif; ?>
                               title="<?= Moncine\View::escape($displayTitle) ?><?= $platformShort !== '' ? ' · ' . Moncine\View::escape($platformShort) : '' ?><?= $annee > 0 ? ' · ' . $annee : '' ?>">
                                <?php if ($hasPoster): ?>
                                    <span class="game-shelf__cover" aria-hidden="true">
                                        <img src="<?= $posterSrc ?>" alt="" loading="lazy" decoding="async">
                                    </span>
                                    <span class="game-shelf__shade" aria-hidden="true"></span>
                                <?php endif; ?>
                                <span class="game-shelf__content">
                                    <span class="game-shelf__title"><?= Moncine\View::escape($displayTitle) ?></span>
                                    <?php if ($annee > 0): ?>
                                        <span class="game-shelf__year"><?= $annee ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                            <?php require MONCINE_ROOT . '/templates/_games_shelf_hover_tile.php'; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="game-shelf__board" aria-hidden="true"></div>
        </section>
    <?php endforeach; ?>
</div>
