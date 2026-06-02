<?php
/**
 * Bouton « Ajouter aux envies » pour un numéro sans support (ni papier ni PDF).
 *
 * @var array<string, mixed> $issue
 * @var int|null $seriesId
 * @var string $possessionFilter
 */
$seriesId = (int) ($seriesId ?? ($issue['series_id'] ?? 0));
$bibId = (int) ($issue['bib_id'] ?? 0);
$possessionFilter = (string) ($possessionFilter ?? Moncine\MagazineRepository::POSSESSION_ALL);
if ($bibId <= 0 || Moncine\MagazineSupport::isPossessed($issue)) {
    return;
}
if (($issue['statut'] ?? '') !== Moncine\LibraryStatut::COLLECTION) {
    return;
}
$inWishlist = (int) ($issue['in_wishlist'] ?? 0) > 0;
?>
<?php if ($inWishlist): ?>
    <span class="magazine-tag magazine-tag--wishlist">En envies</span>
<?php else: ?>
    <form method="post" action="/traiter-numero-magazine.php" class="magazine-wishlist-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="bib_id" value="<?= $bibId ?>">
        <input type="hidden" name="series_id" value="<?= $seriesId ?>">
        <input type="hidden" name="action" value="wishlist">
        <input type="hidden" name="possession" value="<?= Moncine\View::escape($possessionFilter) ?>">
        <button type="submit" class="btn btn-accent btn-sm">Ajouter aux envies</button>
    </form>
<?php endif; ?>
