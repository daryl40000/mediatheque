<?php
/**
 * Colonne gauche de la fiche magazine : couverture, actions rapides.
 *
 * @var array<string, mixed> $issue
 * @var int $bibId
 * @var string $pdfUrl
 * @var string $popoverOpen edit|pdf
 */
$cover = Moncine\View::posterSrc(trim((string) ($issue['poster_url'] ?? '')) ?: null);
$popoverOpen = (string) ($popoverOpen ?? '');
$pdfUrl = trim((string) ($pdfUrl ?? ''));
?>
<aside class="game-detail-sidebar" aria-label="Couverture et infos rapides">
    <?php if ($cover !== ''): ?>
        <img class="film-poster film-poster--large film-poster--bd game-detail-sidebar__poster" src="<?= $cover ?>"
             alt="Couverture de <?= Moncine\View::escape((string) ($issue['series_titre'] ?? 'Numéro')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--bd film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php require MONCINE_ROOT . '/templates/_magazine_detail_action_popovers.php'; ?>
</aside>
