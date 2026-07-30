<?php
/**
 * Mes livres — vue vignettes.
 *
 * @var list<array<string, mixed>> $books
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $viewMode
 */
$gridSortLink = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $viewMode): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(Moncine\View::livresSortUrl($column, $sortBy, $sortDir, $query, false, $viewMode)) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<div class="collection-grid-bar">
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $gridSortLink('Titre', 'titre'); ?>
        <?php $gridSortLink('Auteur', 'auteur'); ?>
        <?php $gridSortLink('Année', 'annee'); ?>
        <?php $gridSortLink('Éditeur', 'editeur'); ?>
    </nav>
</div>

<ul class="collection-grid collection-grid--poster-only" role="list" data-magazine-series-grid>
    <?php foreach ($books as $book):
        $bibId = (int) ($book['bib_id'] ?? 0);
        $posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
        $livreUrl = Moncine\View::livreUrl($bibId);
        $annee = (int) ($book['annee'] ?? 0);
        $displayTitle = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
        $categoryKeys = Moncine\LivreCategory::filterKeysForBook($book);
        $ariaLabel = $displayTitle;
        if ($annee > 0) {
            $ariaLabel .= ', ' . $annee;
        }
        ?>
        <li class="collection-grid__item" role="listitem"
            data-series-categories="<?= Moncine\View::escape(implode(',', $categoryKeys)) ?>">
            <article class="collection-grid__card">
                <a href="<?= Moncine\View::escape($livreUrl) ?>" class="collection-grid__link"
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
                    <?php require MONCINE_ROOT . '/templates/_collection_grid_livre_caption.php'; ?>
                </div>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
