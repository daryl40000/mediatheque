<?php
/**
 * Retirer un numéro des envies ou de la collection (icône ou bouton texte).
 *
 * @var array<string, mixed> $issue
 * @var int|null $seriesId
 * @var string $pageStatut
 * @var string $possessionFilter
 * @var string $variant icon|panel
 */
$bibId = (int) ($issue['bib_id'] ?? 0);
$seriesId = (int) ($seriesId ?? ($issue['series_id'] ?? 0));
$pageStatut = (string) ($pageStatut ?? ($issue['statut'] ?? Moncine\LibraryStatut::COLLECTION));
$possessionFilter = (string) ($possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL);
$variant = (string) ($variant ?? 'icon');
$numeroLabel = trim((string) ($issue['numero'] ?? ''));

if ($bibId <= 0) {
    return;
}

$isWishlist = $pageStatut === Moncine\LibraryStatut::WISHLIST;
$confirmMsg = $isWishlist
    ? 'Retirer le n° ' . $numeroLabel . ' de vos envies ?'
    : 'Retirer le n° ' . $numeroLabel . ' de vos magazines ?';
$title = $isWishlist ? 'Retirer des envies' : 'Retirer de la collection';
$buttonLabel = $isWishlist ? 'Retirer des envies' : 'Retirer de ma collection';
?>
<form method="post"
      action="/traiter-numero-magazine.php"
      class="inline-form magazine-delete-form"
      onsubmit="return confirm(<?= json_encode($confirmMsg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>);">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="bib_id" value="<?= $bibId ?>">
    <input type="hidden" name="series_id" value="<?= $seriesId ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="return_statut" value="<?= Moncine\View::escape($pageStatut) ?>">
    <input type="hidden" name="possession" value="<?= Moncine\View::escape($possessionFilter) ?>">
    <?php if ($variant === 'panel'): ?>
        <button type="submit" class="btn btn-danger btn-sm"><?= Moncine\View::escape($buttonLabel) ?></button>
    <?php else: ?>
        <button type="submit"
                class="btn btn-icon btn-danger-text btn-sm"
                title="<?= Moncine\View::escape($title) ?>"
                aria-label="<?= Moncine\View::escape($title . ($numeroLabel !== '' ? ' — n° ' . $numeroLabel : '')) ?>">
            <svg class="icon-trash" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z"/>
            </svg>
        </button>
    <?php endif; ?>
</form>
