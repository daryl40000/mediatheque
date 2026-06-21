<?php
/**
 * Liste partagée visiteur — vue bibliothèque (tranches verticales).
 *
 * @var list<array<string, mixed>> $films
 * @var string $rawToken
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $kindFilter
 * @var string $viewMode
 */
$shelfSortLink = static function (string $label, string $column) use (
    $rawToken,
    $sortBy,
    $sortDir,
    $query,
    $kindFilter,
    $viewMode
): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(
        Moncine\ShareLinkService::sortUrl($rawToken, $column, $sortBy, $sortDir, $query, $kindFilter, $viewMode)
    ) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
$shelfChunkSize = 28;
$shelves = array_chunk($films, $shelfChunkSize);
$spineHeightPx = Moncine\View::collectionShelfSpineHeightPx();
$shareQueryParams = Moncine\ShareLinkService::collectionQueryParams($query, $sortBy, $sortDir, $kindFilter, $viewMode);
?>
<div class="collection-grid-bar game-shelf-bar">
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $shelfSortLink('Titre', 'titre'); ?>
        <?php $shelfSortLink('Année', 'annee'); ?>
        <?php $shelfSortLink('Réalisateur', 'realisateur'); ?>
    </nav>
</div>

<div class="game-shelf-library" role="list" aria-label="Collection en vue bibliothèque"
     style="--spine-h: <?= (int) $spineHeightPx ?>">
    <?php foreach ($shelves as $shelfFilms): ?>
        <section class="game-shelf" aria-label="Étagère">
            <ul class="game-shelf__spines" role="list">
                <?php foreach ($shelfFilms as $film):
                    $filmId = (int) ($film['id'] ?? 0);
                    $filmUrl = Moncine\ShareLinkService::filmUrl($rawToken, $filmId, $shareQueryParams);
                    $displayTitle = (string) ($film['titre'] ?? '');
                    $annee = (int) ($film['annee'] ?? 0);
                    $kindKey = Moncine\ContentKindFilter::categoryKey($film);
                    $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
                    $hasPoster = $posterSrc !== '';
                    $spineHueStyle = Moncine\View::collectionSpineHueStyle($film);
                    ?>
                    <li class="game-shelf__spine" role="listitem">
                        <article class="game-shelf__card">
                            <a href="<?= Moncine\View::escape($filmUrl) ?>"
                               class="game-shelf__link<?= $hasPoster ? ' game-shelf__link--poster' : ' game-shelf__link--fallback' ?>"
                               <?php if (!$hasPoster): ?>style="<?= Moncine\View::escape($spineHueStyle) ?>"<?php endif; ?>
                               title="<?= Moncine\View::escape($displayTitle) ?><?= $annee > 0 ? ' · ' . $annee : '' ?>">
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
                            <?php require MONCINE_ROOT . '/templates/_films_shelf_hover_tile.php'; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="game-shelf__board" aria-hidden="true"></div>
        </section>
    <?php endforeach; ?>
</div>
