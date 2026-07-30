<?php
/**
 * Mes livres — vue bibliothèque (tranches).
 *
 * @var list<array<string, mixed>> $books
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $viewMode
 */
$shelfSortLink = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(Moncine\View::livresSortUrl($column, $sortBy, $sortDir, $query, false, $viewMode)) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
$shelfChunkSize = 28;
$shelves = array_chunk($books, $shelfChunkSize);
$spineHeightPx = Moncine\View::collectionShelfSpineHeightPx();
?>
<div class="collection-grid-bar game-shelf-bar">
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $shelfSortLink('Titre', 'titre'); ?>
        <?php $shelfSortLink('Auteur', 'auteur'); ?>
        <?php $shelfSortLink('Année', 'annee'); ?>
        <?php $shelfSortLink('Éditeur', 'editeur'); ?>
    </nav>
</div>

<div class="game-shelf-library" role="list" aria-label="Collection en vue bibliothèque"
     style="--spine-h: <?= (int) $spineHeightPx ?>"
     data-magazine-series-grid>
    <?php foreach ($shelves as $shelfBooks): ?>
        <section class="game-shelf" aria-label="Étagère">
            <ul class="game-shelf__spines" role="list">
                <?php foreach ($shelfBooks as $book):
                    $bibId = (int) ($book['bib_id'] ?? 0);
                    $livreUrl = Moncine\View::livreUrl($bibId);
                    $displayTitle = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
                    $annee = (int) ($book['annee'] ?? 0);
                    $posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
                    $hasPoster = $posterSrc !== '';
                    $spineHueStyle = Moncine\View::collectionSpineHueStyle(array_merge($book, [
                        'id' => $bibId,
                        'media_domain' => Moncine\MediaDomain::LIVRE,
                    ]));
                    $categoryKeys = Moncine\LivreCategory::filterKeysForBook($book);
                    ?>
                    <li class="game-shelf__spine" role="listitem"
                        data-series-categories="<?= Moncine\View::escape(implode(',', $categoryKeys)) ?>">
                        <article class="game-shelf__card">
                            <a href="<?= Moncine\View::escape($livreUrl) ?>"
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
                            <?php require MONCINE_ROOT . '/templates/_livres_shelf_hover_tile.php'; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="game-shelf__board" aria-hidden="true"></div>
        </section>
    <?php endforeach; ?>
</div>
