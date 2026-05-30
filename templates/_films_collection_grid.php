<?php
/**
 * Mes films — vue vignettes (grille d’affiches).
 *
 * @var list<array<string, mixed>> $films
 * @var string $sortBy
 * @var string $sortDir
 * @var string $query
 * @var string $kindFilter
 * @var string $viewMode
 * @var Moncine\FilmListContext $filmListContext
 */
$filmListContext = $filmListContext ?? Moncine\FilmListContext::forCollection($sortBy, $sortDir, $query, $kindFilter);
$gridSortLink = static function (string $label, string $column) use ($sortBy, $sortDir, $query, $kindFilter, $viewMode): void {
    $active = $sortBy === $column;
    $class = 'collection-grid-sort__link' . ($active ? ' is-active' : '');
    ?>
    <a href="<?= Moncine\View::escape(Moncine\View::filmsSortUrl($column, $sortBy, $sortDir, $query, $kindFilter, $viewMode)) ?>"
       class="<?= $class ?>">
        <?= Moncine\View::escape($label) ?><?= Moncine\View::filmsSortIndicator($column, $sortBy, $sortDir) ?>
    </a>
    <?php
};
?>
<div class="collection-grid-bar">
    <label class="collection-grid-bar__select-all collection-select-all">
        <input type="checkbox" id="collection-select-all" aria-label="Tout sélectionner sur cette page">
        <span>Tout sélectionner</span>
    </label>
    <nav class="collection-grid-sort" aria-label="Trier">
        <span class="collection-grid-sort__label">Trier par</span>
        <?php $gridSortLink('Titre', 'titre'); ?>
        <?php $gridSortLink('Année', 'annee'); ?>
        <?php $gridSortLink('Note', 'note'); ?>
        <?php $gridSortLink('Dernière vue', 'derniere_vue'); ?>
    </nav>
</div>

<ul class="collection-grid" role="list">
    <?php foreach ($films as $film):
        $filmId = (int) $film['id'];
        $posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
        $filmUrl = $filmListContext->filmUrl($filmId);
        $annee = (int) ($film['annee'] ?? 0);
        $kindKey = \Moncine\ContentKindFilter::categoryKey($film);
        ?>
        <li class="collection-grid__item" role="listitem">
            <article class="collection-grid__card">
                <label class="collection-grid__check" title="Sélectionner">
                    <input type="checkbox" name="film_ids[]"
                           value="<?= $filmId ?>"
                           class="collection-film-cb"
                           aria-label="Sélectionner <?= Moncine\View::escape($film['titre']) ?>">
                </label>
                <a href="<?= Moncine\View::escape($filmUrl) ?>" class="collection-grid__link">
                    <div class="collection-grid__poster-wrap">
                        <?php if ($posterSrc !== ''): ?>
                            <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                                 alt="Affiche de <?= Moncine\View::escape($film['titre']) ?>"
                                 width="140" height="210" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="collection-grid__poster collection-grid__poster--empty"
                                  aria-hidden="true"></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="collection-grid__title"><?= Moncine\View::escape($film['titre']) ?></h3>
                    <p class="collection-grid__meta">
                        <span class="tag tag--kind tag--kind-<?= Moncine\View::escape($kindKey) ?>">
                            <?= Moncine\View::escape(\Moncine\ContentKindFilter::listLabel($film)) ?>
                        </span>
                        <?php if ($annee > 0): ?>
                            <span class="collection-grid__year"><?= $annee ?></span>
                        <?php endif; ?>
                        <?php
                        $showFoyerAverage = true;
                        $layout = 'inline';
                        ob_start();
                        require MONCINE_ROOT . '/templates/_film_ratings.php';
                        $ratingsHtml = trim((string) ob_get_clean());
                        if ($ratingsHtml !== '' && $ratingsHtml !== '—'):
                            ?>
                            <span class="collection-grid__note"><?= $ratingsHtml ?></span>
                        <?php endif; ?>
                    </p>
                </a>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
