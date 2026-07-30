<?php
/**
 * Boutons vers les magazines et livres liés à un jeu.
 *
 * @var int $magazineIssueCount
 * @var int $livreBookCount
 * @var int $oeuvreId
 * @var int $bibId
 */
$magazineIssueCount = (int) ($magazineIssueCount ?? 0);
$livreBookCount = (int) ($livreBookCount ?? 0);
$oeuvreId = (int) ($oeuvreId ?? 0);
$bibId = (int) ($bibId ?? 0);
if ($oeuvreId <= 0 || ($magazineIssueCount <= 0 && $livreBookCount <= 0)) {
    return;
}
?>
<p class="game-detail__magazines-link game-detail__media-links">
    <?php if ($magazineIssueCount > 0): ?>
        <a href="<?= Moncine\View::escape(Moncine\View::gameMagazinesUrl($oeuvreId, $bibId)) ?>"
           class="btn btn-secondary">
            Magazines
        </a>
    <?php endif; ?>
    <?php if ($livreBookCount > 0): ?>
        <a href="<?= Moncine\View::escape(Moncine\View::gameLivresUrl($oeuvreId, $bibId)) ?>"
           class="btn btn-secondary">
            Livres
        </a>
    <?php endif; ?>
</p>
