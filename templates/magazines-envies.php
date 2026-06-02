<?php
/** @var list<array<string, mixed>> $seriesList */
/** @var string $query */
/** @var int $totalCount */
/** @var string $moduleError */
?>
<section class="collection-page">
    <header class="collection-page__header">
        <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></h1>
        <p class="lead">Numéros que vous souhaitez acquérir, regroupés par série.</p>
        <p><a href="/magazines.php" class="btn btn-secondary btn-sm">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>
    </header>

    <?php if ($moduleError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($moduleError) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Numéro retiré de vos envies.</div>
    <?php endif; ?>

    <?php if ($totalCount === 0): ?>
        <p class="hint">Aucune envie magazine pour l’instant.</p>
    <?php else: ?>
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
