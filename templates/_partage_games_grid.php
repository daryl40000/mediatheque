<?php
/**
 * Liste partagée visiteur — jeux, vue vignettes.
 *
 * @var list<array<string, mixed>> $games
 * @var string $rawToken
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $viewMode
 */
$mediaDomain = Moncine\MediaDomain::JEU;
$listContext = Moncine\ShareLinkService::collectionQueryParams($query, $sortBy, $sortDir, '', $viewMode);
$gridSortLink = static function (string $label, string $column) use (
    $rawToken,
    $sortBy,
    $sortDir,
    $query,
    $viewMode,
    $mediaDomain
): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\ShareLinkService::sortUrl($rawToken, $column, $sortBy, $sortDir, $query, '', $viewMode, $mediaDomain)
    ) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<div class="collection-grid-bar collection-grid-bar--games">
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $gridSortLink('Titre', 'titre'); ?>
        <?php $gridSortLink('Année', 'annee'); ?>
        <?php $gridSortLink('Studio', 'studio'); ?>
    </nav>
</div>

<ul class="collection-grid collection-grid--games" role="list">
    <?php foreach ($games as $game):
        $gameId = (int) ($game['id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($game['poster_url'] ?? null);
        $gameUrl = Moncine\ShareLinkService::gameUrl($rawToken, $gameId, $listContext);
        $annee = (int) ($game['annee'] ?? 0);
        $platformShort = (string) ($game['platform_short'] ?? '');
        $displayTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? '');
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($gameUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Jaquette de <?= Moncine\View::escape($displayTitle) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty" aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <div class="collection-grid__caption">
                        <h3 class="collection-grid__title collection-grid__title--game">
                            <?= Moncine\View::escape($displayTitle) ?>
                        </h3>
                        <p class="collection-grid__meta">
                            <?php if ($platformShort !== ''): ?>
                                <span class="magazine-tag magazine-tag--game-platform"><?= Moncine\View::escape($platformShort) ?></span>
                            <?php endif; ?>
                            <?php if ($annee > 0): ?>
                                <span class="collection-grid__year"><?= $annee ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
