<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var string $sortBy */
/** @var string $sortDir */
/** @var int $totalCount */
/** @var string $moduleError */
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></h1>
        <p class="lead">
            Vos bandes dessinées et mangas sont regroupées par <strong>série</strong>
            (Astérix, One Piece…). Créez d’abord une série, puis ajoutez les tomes.
        </p>
        <div class="collection-page__actions">
            <a class="btn btn-secondary" href="/gerer-partages.php?domain=<?= Moncine\MediaDomain::BD ?>&scope=<?= Moncine\ShareLinkScope::COLLECTION ?>">
                Partager
            </a>
            <a href="/ajouter-serie-bd.php" class="btn btn-accent">Ajouter une série</a>
        </div>
    </header>

    <nav class="ui-pill-nav" aria-label="Navigation BD">
        <a href="/bd-envies.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></a>
        <a href="/statistiques.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['stats']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <form method="get" action="/bd.php" class="collection-search import-form">
        <label for="bd_q">Rechercher une série</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="bd_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre, éditeur…">
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
        </div>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune série en collection. Commencez par <a href="/ajouter-serie-bd.php">créer une série</a>, puis ajoutez des tomes.</p>
    <?php else: ?>
        <p class="stats"><?= (int) $totalCount ?> série<?= $totalCount > 1 ? 's' : '' ?> en collection.</p>
        <div class="magazine-series-grid">
            <?php foreach ($seriesList as $series): ?>
                <?php
                $seriesId = (int) ($series['id'] ?? 0);
                $posterSrc = Moncine\View::seriesPosterSrc($series);
                $possessedCount = (int) ($series['possessed_tome_count'] ?? $series['tome_count'] ?? 0);
                $catalogCount = (int) ($series['catalog_tome_count'] ?? 0);
                ?>
                <a href="<?= Moncine\View::escape(Moncine\View::bdSeriesUrl($seriesId)) ?>"
                   class="magazine-series-card">
                    <?php if ($posterSrc !== ''): ?>
                        <img src="<?= $posterSrc ?>" alt="" class="magazine-series-card__cover" loading="lazy">
                    <?php else: ?>
                        <div class="magazine-series-card__cover magazine-series-card__cover--empty" aria-hidden="true"></div>
                    <?php endif; ?>
                    <div class="magazine-series-card__body">
                        <h2 class="magazine-series-card__title"><?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?></h2>
                        <p class="hint">
                            <?= Moncine\View::escape((string) ($series['kind_label'] ?? '')) ?>
                            · <?= $possessedCount ?> possédé<?= $possessedCount > 1 ? 's' : '' ?> sur <?= $catalogCount ?>
                            <?php if ($possessedCount === 0 && $catalogCount === 0): ?>
                                — ajoutez le premier tome
                            <?php endif; ?>
                        </p>
                        <?php if (trim((string) ($series['editeur'] ?? '')) !== ''): ?>
                            <p class="hint"><?= Moncine\View::escape((string) $series['editeur']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
