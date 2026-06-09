<?php
/**
 * Formulaire « J’ai acheté » pour passer une envie jeu en collection.
 *
 * @var int $gameId
 * @var string $return fiche|envies
 * @var string $formClass
 * @var list<array{0: string, 1: string}> $extraHiddenFields
 */
$return = $return ?? 'fiche';
$formClass = $formClass ?? 'wishlist-promote-form import-form';
$extraHiddenFields = $extraHiddenFields ?? [];
?>
<form method="post" action="/promouvoir-jeu-collection.php"
      class="<?= Moncine\View::escape($formClass) ?><?= $formClass !== 'wishlist-promote-form import-form' ? '' : ' wishlist-promote-form--compact' ?>">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="game_id" value="<?= (int) $gameId ?>">
    <input type="hidden" name="return" value="<?= Moncine\View::escape($return) ?>">
    <?php foreach ($extraHiddenFields as $field): ?>
        <input type="hidden" name="<?= Moncine\View::escape((string) ($field[0] ?? '')) ?>"
               value="<?= Moncine\View::escape((string) ($field[1] ?? '')) ?>">
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-sm">J’ai acheté</button>
</form>
