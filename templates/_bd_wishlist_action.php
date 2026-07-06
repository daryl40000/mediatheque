<?php
/**
 * Bouton « Ajouter aux envies » pour un tome non possédé (collection).
 *
 * @var int $albumId
 * @var bool $inWishlist
 */
$albumId = (int) ($albumId ?? 0);
$inWishlist = !empty($inWishlist);
if ($albumId <= 0) {
    return;
}
?>
<?php if ($inWishlist): ?>
    <span class="btn btn-icon btn-secondary btn-sm game-detail-sidebar__wishlist-badge"
          title="Déjà dans vos envies"
          aria-label="Déjà dans vos envies">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M12 21s-7-4.35-10-9.5C-0.2 6.9 2.2 3.5 6 3.5c2 0 3.2 1 4 2 0.8-1 2-2 4-2 3.8 0 6.2 3.4 4 8C19 16.65 12 21 12 21z"/>
        </svg>
    </span>
<?php else: ?>
    <form method="post" action="/traiter-tome-bd.php" class="inline-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <input type="hidden" name="action" value="wishlist">
        <button type="submit" class="btn btn-icon btn-secondary btn-sm"
                title="Ajouter aux envies" aria-label="Ajouter aux envies">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 21s-7-4.35-10-9.5C-0.2 6.9 2.2 3.5 6 3.5c2 0 3.2 1 4 2 0.8-1 2-2 4-2 3.8 0 6.2 3.4 4 8C19 16.65 12 21 12 21z"/>
            </svg>
        </button>
    </form>
<?php endif; ?>
