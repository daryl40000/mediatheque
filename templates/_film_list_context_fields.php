<?php
/**
 * Champs cachés pour conserver tri / recherche / liste sur les formulaires de fiche film.
 *
 * @var Moncine\FilmListContext $filmListContext
 */
if (!isset($filmListContext)) {
    return;
}
foreach ($filmListContext->queryParams() as $name => $value): ?>
    <input type="hidden" name="<?= Moncine\View::escape($name) ?>" value="<?= Moncine\View::escape($value) ?>">
<?php endforeach; ?>
