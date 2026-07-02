<?php
/** @var array<string, mixed>|null $link */
/** @var list<array<string, mixed>> $seriesList */
?>
<section class="collection-page share-visitor-page">
    <?php if ($link === null): ?>
        <h1>Lien invalide ou expiré</h1>
        <p class="hint">Ce lien de partage n’existe pas, a expiré ou a été révoqué.</p>
    <?php else:
        $sortBy = $sortBy ?? 'titre';
        $sortDir = $sortDir ?? 'asc';
        $query = $query ?? '';
        $rawToken = (string) ($rawToken ?? '');
        $mediaDomain = Moncine\MediaDomain::BD;

        $shareSortUrl = static function (string $column) use (
            $rawToken,
            $sortBy,
            $sortDir,
            $query,
            $mediaDomain
        ): string {
            $dir = 'asc';
            if ($sortBy === $column && strtolower($sortDir) === 'asc') {
                $dir = 'desc';
            }

            return Moncine\ShareLinkService::collectionUrl(
                $rawToken,
                Moncine\ShareLinkService::collectionQueryParams($query, $column, $dir),
                $mediaDomain
            );
        };
        ?>
        <header class="share-visitor-header">
            <h1><?= Moncine\View::escape((string) ($scopeLabel ?? 'Liste partagée')) ?></h1>
            <p class="lead">
                Liste partagée par <strong><?= Moncine\View::escape((string) ($ownerLabel ?? '')) ?></strong>
                — lecture seule.
            </p>
        </header>

        <form method="get" action="/partage-bd.php" class="collection-search import-form">
            <input type="hidden" name="t" value="<?= Moncine\View::escape($rawToken) ?>">
            <?php if ($sortBy !== 'titre'): ?>
                <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
            <?php endif; ?>
            <?php if (strtolower($sortDir) === 'desc'): ?>
                <input type="hidden" name="dir" value="desc">
            <?php endif; ?>
            <label for="share_bd_q">Rechercher une série</label>
            <div class="collection-search__row">
                <input type="search" name="q" id="share_bd_q"
                       value="<?= Moncine\View::escape($query) ?>"
                       placeholder="Titre, éditeur…">
                <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
            </div>
        </form>

        <?php if ($seriesList === []): ?>
            <p class="hint">Aucune série à afficher.</p>
        <?php else: ?>
            <p class="stats"><?= (int) ($totalCount ?? count($seriesList)) ?> série<?= (int) ($totalCount ?? count($seriesList)) > 1 ? 's' : '' ?></p>
            <nav class="collection-grid-sort" aria-label="Trier">
                <span class="collection-grid-sort__label">Trier par</span>
                <a href="<?= Moncine\View::escape($shareSortUrl('titre')) ?>"
                   class="collection-grid-sort__link<?= $sortBy === 'titre' ? ' is-active' : '' ?>">Titre</a>
                <a href="<?= Moncine\View::escape($shareSortUrl('tomes')) ?>"
                   class="collection-grid-sort__link<?= $sortBy === 'tomes' ? ' is-active' : '' ?>">Tomes</a>
            </nav>
            <div class="magazine-series-grid">
                <?php foreach ($seriesList as $series): ?>
                    <?php
                    $seriesId = (int) ($series['id'] ?? 0);
                    $seriesUrl = Moncine\ShareLinkService::bdSeriesUrl(
                        $rawToken,
                        $seriesId,
                        Moncine\ShareLinkService::collectionQueryParams($query, $sortBy, $sortDir)
                    );
                    $posterSrc = Moncine\View::seriesPosterSrc($series);
                    $possessedCount = (int) ($series['possessed_tome_count'] ?? $series['tome_count'] ?? 0);
                    $catalogCount = (int) ($series['catalog_tome_count'] ?? 0);
                    ?>
                    <a href="<?= Moncine\View::escape($seriesUrl) ?>" class="magazine-series-card">
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
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
