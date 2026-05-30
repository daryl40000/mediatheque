<?php
/**
 * Bloc de notation (inclus sur resultat et meilleurs).
 *
 * @var int $filmId
 * @var string|null $currentRating
 * @var array<string, array{label: string, score: int}> $noteLevels
 * @var string $returnPage resultat|meilleurs
 */
?>
<div class="rating-block">
    <div class="rating-buttons" role="group" aria-label="Choix pour la proposition">
        <?php foreach ($noteLevels as $key => $meta):
            $active = $currentRating === $key ? ' rating-btn--active' : '';
            ?>
            <form method="post" action="/noter.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
                <input type="hidden" name="note" value="<?= Moncine\View::escape($key) ?>">
                <input type="hidden" name="return" value="<?= Moncine\View::escape($returnPage) ?>">
                <button type="submit" class="rating-btn rating-btn--<?= Moncine\View::escape($key) ?><?= $active ?>">
                    <?= Moncine\View::escape($meta['label']) ?>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
