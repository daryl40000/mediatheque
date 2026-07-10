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
        <?php if (Moncine\GamePlaytime::isAvailable()): ?>
        <?php $gridSortLink('Temps de jeu', 'steam_playtime'); ?>
        <?php endif; ?>
    </nav>
</div>

<ul class="collection-grid collection-grid--games collection-grid--poster-only" role="list">
    <?php foreach ($games as $game):
        $bibId = (int) ($game['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $gameUrl = Moncine\View::gameUrl($bibId);
        $annee = (int) ($game['annee'] ?? 0);
        $platformShort = (string) ($game['platform_short'] ?? '');
        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
        $ariaLabel = $displayTitle;
        if ($platformShort !== '') {
            $ariaLabel .= ' — ' . $platformShort;
        }
        if ($annee > 0) {
            $ariaLabel .= ', ' . $annee;
        }
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
                <a href="<?= Moncine\View::escape($gameUrl) ?>" class="collection-grid__link"
                   aria-label="<?= Moncine\View::escape($ariaLabel) ?>">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt=""
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="collection-grid__hover-bubble" aria-hidden="true">
                    <?php require MONCINE_ROOT . '/templates/_collection_grid_game_caption.php'; ?>
                </div>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
