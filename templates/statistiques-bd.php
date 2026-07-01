<?php
/** @var int $seriesCount */
/** @var int $tomeCount */
/** @var int $wishlistSeriesCount */
?>
<section class="collection-page">
    <h1><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['stats']) ?></h1>
    <p class="lead">Vue d’ensemble de votre collection BD / manga.</p>

    <nav class="ui-pill-nav" aria-label="Navigation BD">
        <a href="/bd.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a>
        <a href="/bd-envies.php" class="ui-pill"><?= Moncine\View::escape(Moncine\MediaContext::navLabels()['wishlist']) ?></a>
    </nav>

    <ul class="stats-summary">
        <li><strong><?= (int) $seriesCount ?></strong> série<?= $seriesCount > 1 ? 's' : '' ?> en collection</li>
        <li><strong><?= (int) $tomeCount ?></strong> tome<?= $tomeCount > 1 ? 's' : '' ?> possédé<?= $tomeCount > 1 ? 's' : '' ?> en collection</li>
        <li><strong><?= (int) $wishlistSeriesCount ?></strong> envie<?= $wishlistSeriesCount > 1 ? 's' : '' ?> (séries)</li>
    </ul>
</section>
