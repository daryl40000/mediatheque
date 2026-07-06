<?php
/**
 * Colonne gauche de la fiche BD : couverture, statut lu, actions rapides.
 *
 * @var array<string, mixed> $album
 * @var int $albumId
 * @var bool $isWishlist
 * @var bool $everRead
 * @var string $readAtLabel
 * @var string $popoverOpen note|edit|lu
 */
$posterSrc = Moncine\View::posterSrc($album['poster_url'] ?? null);
$isWishlist = $isWishlist ?? false;
$everRead = $everRead ?? false;
$readAtLabel = trim((string) ($readAtLabel ?? ''));
$popoverOpen = (string) ($popoverOpen ?? '');
?>
<aside class="game-detail-sidebar" aria-label="Couverture et infos rapides">
    <?php if ($posterSrc !== ''): ?>
        <img class="film-poster film-poster--large film-poster--bd game-detail-sidebar__poster" src="<?= $posterSrc ?>"
             alt="Couverture de <?= Moncine\View::escape((string) ($album['titre'] ?? $album['display_titre'] ?? 'Album')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--bd film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php if (!$isWishlist && $everRead): ?>
        <p class="game-detail-sidebar__finished">
            <span class="game-detail-sidebar__badge">Lu</span>
            <?php if ($readAtLabel !== ''): ?>
                <span class="game-detail-sidebar__finished-date"><?= Moncine\View::escape($readAtLabel) ?></span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!$isWishlist): ?>
        <?php require MONCINE_ROOT . '/templates/_bd_detail_action_popovers.php'; ?>
    <?php endif; ?>
</aside>
