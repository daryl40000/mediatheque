<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */
?>
<section class="collection-page wishlist-page">
    <div class="collection-page__head">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></h1>
        <a class="btn btn-primary" href="/ajouter-serie-bd.php?statut=wishlist">Ajouter une envie (série)</a>
    </div>

    <p class="lead">Séries BD / manga que vous souhaitez acquérir.</p>

    <nav class="ui-pill-nav" aria-label="Navigation envies BD">
        <a href="/bd.php" class="ui-pill">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <form method="get" action="/bd-envies.php" class="collection-search import-form">
        <label for="bd_w_q">Rechercher</label>
        <input type="search" name="q" id="bd_w_q"
               value="<?= Moncine\View::escape($query) ?>"
               placeholder="Titre de série…">
        <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune envie BD. <a href="/ajouter-serie-bd.php">Ajouter une série</a>.</p>
    <?php else: ?>
        <p class="hint"><?= (int) $totalCount ?> série<?= $totalCount > 1 ? 's' : '' ?> en envies.</p>
        <div class="magazine-series-grid">
            <?php foreach ($seriesList as $series): ?>
                <?php
                $seriesId = (int) ($series['id'] ?? 0);
                $posterSrc = Moncine\View::posterSrc(trim((string) ($series['poster_url'] ?? '')) ?: null);
                ?>
                <a href="<?= Moncine\View::escape(Moncine\View::bdSeriesUrl($seriesId, 'tome', 'asc', ['statut' => Moncine\LibraryStatut::WISHLIST])) ?>"
                   class="magazine-series-card">
                    <?php if ($posterSrc !== ''): ?>
                        <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                    <?php else: ?>
                        <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="magazine-series-card__body">
                        <h2 class="magazine-series-card__title"><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h2>
                        <p class="hint"><?= Moncine\View::escape((string) ($series['kind_label'] ?? '')) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
