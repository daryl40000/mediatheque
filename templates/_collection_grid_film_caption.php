<?php
/**
 * Légende d’une vignette film (titre, type, année, notes).
 *
 * @var array<string, mixed> $film
 * @var int $annee
 * @var string $kindKey
 */
$annee = (int) ($annee ?? 0);
$kindKey = (string) ($kindKey ?? \Moncine\ContentKindFilter::categoryKey($film));
?>
<div class="collection-grid__caption">
    <h3 class="collection-grid__title"><?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?></h3>
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
