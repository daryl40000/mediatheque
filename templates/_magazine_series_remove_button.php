<?php
/**
 * Retirer une série magazine de la collection ou des envies.
 *
 * @var array<string, mixed> $series
 * @var string $pageStatut
 * @var int $issueCount
 * @var string $variant panel|compact
 */
$seriesId = (int) ($series['id'] ?? 0);
$pageStatut = (string) ($pageStatut ?? Moncine\LibraryStatut::COLLECTION);
$issueCount = (int) ($issueCount ?? 0);
$variant = (string) ($variant ?? 'panel');
$seriesTitre = trim((string) ($series['titre'] ?? ''));

if ($seriesId <= 0) {
    return;
}

$isWishlist = $pageStatut === Moncine\LibraryStatut::WISHLIST;
$buttonLabel = $isWishlist ? 'Retirer de mes envies' : 'Retirer de mes magazines';
$title = $isWishlist
    ? 'Retirer cette série de vos envies'
    : 'Retirer cette série de vos magazines';

$confirmParts = [
    $isWishlist
        ? 'Retirer « ' . $seriesTitre . ' » de vos envies ?'
        : 'Retirer « ' . $seriesTitre . ' » de vos magazines ?',
];
if ($issueCount > 0) {
    $confirmParts[] = $issueCount . ' numéro(s) seront retirés de votre bibliothèque.';
}
$confirmParts[] = 'Le catalogue partagé (fiches revue et numéros) ne sera pas supprimé.';
$confirmMsg = implode("\n", $confirmParts);
?>
<?php if ($variant === 'panel'): ?>
<section class="magazine-series-remove-panel film-delete-panel">
    <h2 class="magazine-series-remove-panel__title film-delete-panel__title"><?= Moncine\View::escape($title) ?></h2>
    <p class="hint">
        La revue disparaîtra de votre liste<?= $isWishlist ? ' d’envies' : '' ?>.
        Vous pourrez la rajouter plus tard depuis le catalogue.
    </p>
    <form method="post"
          action="/traiter-serie-magazine.php"
          class="inline-form"
          onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">
        <input type="hidden" name="action" value="remove_series">
        <input type="hidden" name="return_statut" value="<?= Moncine\View::escape($pageStatut) ?>">
        <button type="submit" class="btn btn-danger btn-sm"><?= Moncine\View::escape($buttonLabel) ?></button>
    </form>
</section>
<?php else: ?>
<form method="post"
      action="/traiter-serie-magazine.php"
      class="inline-form magazine-series-remove-form"
      onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="series_id" value="<?= $seriesId ?>">
    <input type="hidden" name="action" value="remove_series">
    <input type="hidden" name="return_statut" value="<?= Moncine\View::escape($pageStatut) ?>">
    <button type="submit" class="btn btn-danger-text btn-sm"><?= Moncine\View::escape($buttonLabel) ?></button>
</form>
<?php endif; ?>
