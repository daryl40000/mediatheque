<?php
/**
 * Bouton unique vers la liste des magazines d’un jeu.
 *
 * @var int $magazineIssueCount
 * @var int $oeuvreId
 * @var int $bibId
 */
$magazineIssueCount = (int) ($magazineIssueCount ?? 0);
$oeuvreId = (int) ($oeuvreId ?? 0);
$bibId = (int) ($bibId ?? 0);
if ($magazineIssueCount <= 0 || $oeuvreId <= 0) {
    return;
}
?>
<p class="game-detail__magazines-link">
    <a href="<?= Moncine\View::escape(Moncine\View::gameMagazinesUrl($oeuvreId, $bibId)) ?>"
       class="btn btn-secondary">
        Magazines
    </a>
</p>
