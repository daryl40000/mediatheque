<?php
/**
 * Tuile vignette affichée au survol d’une tranche film (vue bibliothèque).
 *
 * @var array<string, mixed> $film
 * @var string $filmUrl
 * @var string $posterSrc
 * @var string $displayTitle
 * @var int $annee
 * @var string $kindKey
 */
$annee = (int) ($annee ?? 0);
$kindKey = (string) ($kindKey ?? '');
$posterSrc = (string) ($posterSrc ?? '');
?>
<div class="game-shelf__preview" aria-hidden="true">
    <article class="collection-grid__card game-shelf__preview-card">
        <a href="<?= Moncine\View::escape($filmUrl) ?>" class="collection-grid__link" tabindex="-1">
            <div class="collection-grid__poster-wrap">
                <?php if ($posterSrc !== ''): ?>
                    <img class="collection-grid__poster" src="<?= $posterSrc ?>"
                         alt="Affiche de <?= Moncine\View::escape($displayTitle) ?>"
                         width="140" height="210" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="collection-grid__poster collection-grid__poster--empty"
                          aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div class="collection-grid__caption">
                <h3 class="collection-grid__title"><?= Moncine\View::escape($displayTitle) ?></h3>
                <p class="collection-grid__meta">
                    <span class="tag tag--kind tag--kind-<?= Moncine\View::escape($kindKey) ?>">
                        <?= Moncine\View::escape(\Moncine\ContentKindFilter::listLabel($film)) ?>
                    </span>
                    <?php if ($annee > 0): ?>
                        <span class="collection-grid__year"><?= $annee ?></span>
                    <?php endif; ?>
                </p>
                <div class="collection-grid__ratings">
                    <?php
                    $showFoyerAverage = true;
                    $layout = 'stacked';
                    ob_start();
                    require MONCINE_ROOT . '/templates/_film_ratings.php';
                    $ratingsHtml = trim((string) ob_get_clean());
                    if ($ratingsHtml !== '' && $ratingsHtml !== '—') {
                        echo $ratingsHtml;
                    }
                    ?>
                </div>
            </div>
        </a>
    </article>
</div>
