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
        <div class="collection-page__head-actions">
            <?php
            $printUrl = Moncine\View::magazinesWishlistPrintUrl($query ?? '', $sortBy ?? 'titre', $sortDir ?? 'asc');
            require MONCINE_ROOT . '/templates/_print_button.php';
            ?>
            <a class="btn btn-secondary" href="/gerer-partages.php?domain=<?= Moncine\MediaDomain::MAGAZINE ?>&scope=<?= Moncine\ShareLinkScope::WISHLIST ?>">
                Partager
            </a>
            <a class="btn btn-primary" href="/ajouter-serie-magazine.php?statut=wishlist">Ajouter une envie (série)</a>
        </div>
    </div>

    <p class="lead">Numéros que vous souhaitez acquérir, regroupés par série.</p>

    <nav class="ui-pill-nav" aria-label="Navigation envies magazines">
        <a href="/magazines.php" class="ui-pill">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a>
    </nav>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Numéro retiré de vos envies.</div>
    <?php endif; ?>

    <?php if (isset($_GET['series_removed'])): ?>
        <div class="alert alert-success">
            La revue a été retirée de vos envies.
            <?php if (isset($_GET['removed_issues']) && (int) $_GET['removed_issues'] > 0): ?>
                <?= (int) $_GET['removed_issues'] ?> numéro(s) concerné(s).
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="get" action="/magazines-envies.php" class="collection-search import-form">
        <label for="mag_w_q">Rechercher</label>
        <div class="collection-search__row">
            <input type="search" name="q" id="mag_w_q"
                   value="<?= Moncine\View::escape($query) ?>"
                   placeholder="Titre de série…">
            <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
        </div>
    </form>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune envie magazine pour l’instant.</p>
    <?php else: ?>
        <p class="hint"><?= (int) $totalCount ?> série<?= $totalCount > 1 ? 's' : '' ?> en envies.</p>
        <ul class="magazine-series-list">
            <?php foreach ($seriesList as $series): ?>
                <li>
                    <a href="<?= Moncine\View::escape(Moncine\View::magazineSeriesUrl((int) ($series['id'] ?? 0), 'numero_ordre', 'desc', ['statut' => 'wishlist'])) ?>">
                        <?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>
                    </a>
                    <span class="hint"> — <?= (int) ($series['issue_count'] ?? 0) ?> numéro(s) recherché(s)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
