<?php
/**
 * Bouton « Retirer » : enlève un film des envies sans le mettre dans « Mes films ».
 *
 * Variables attendues (déjà définies par la page qui inclut ce fichier) :
 * - $filmId : numéro du film dans la bibliothèque
 * - $filmTitle : titre affiché dans la question de confirmation
 * - $sortBy, $sortDir, $query, $scope : pour revenir sur la même liste après le retrait
 */
$filmId = (int) ($filmId ?? 0);
$filmTitle = (string) ($filmTitle ?? '');
$sortBy = (string) ($sortBy ?? 'titre');
$sortDir = (string) ($sortDir ?? 'asc');
$query = (string) ($query ?? '');
$scope = (string) ($scope ?? Moncine\WishlistScope::MINE);

if ($filmId <= 0) {
    return;
}

$confirmMessage = 'Retirer « ' . $filmTitle . ' » de vos envies ?';
?>
<form method="post"
      action="/souhaits.php"
      class="inline-form wishlist-remove-form"
      onsubmit="return confirm(<?= json_encode($confirmMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="film_id" value="<?= $filmId ?>">
    <input type="hidden" name="sort" value="<?= Moncine\View::escape($sortBy) ?>">
    <input type="hidden" name="dir" value="<?= Moncine\View::escape($sortDir) ?>">
    <input type="hidden" name="q" value="<?= Moncine\View::escape($query) ?>">
    <?php if ($scope === Moncine\WishlistScope::GROUP): ?>
        <input type="hidden" name="scope" value="<?= Moncine\View::escape(Moncine\WishlistScope::GROUP) ?>">
    <?php endif; ?>
    <button type="submit" class="btn btn-danger-text btn-sm">Retirer</button>
</form>
