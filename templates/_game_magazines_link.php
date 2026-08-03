<?php
/**
 * Boutons vers les magazines et livres liés à un jeu ou un film.
 *
 * @var int $magazineIssueCount
 * @var int $livreBookCount
 * @var int $oeuvreId
 * @var int $bibId
 * @var string|null $magazinesListUrl URL liste magazines (défaut : page jeux)
 */
$magazineIssueCount = (int) ($magazineIssueCount ?? 0);
$livreBookCount = (int) ($livreBookCount ?? 0);
$oeuvreId = (int) ($oeuvreId ?? 0);
$bibId = (int) ($bibId ?? 0);
$magazinesListUrl = trim((string) ($magazinesListUrl ?? ''));
if ($magazinesListUrl === '') {
    $magazinesListUrl = Moncine\View::gameMagazinesUrl($oeuvreId, $bibId);
}
if ($oeuvreId <= 0 || ($magazineIssueCount <= 0 && $livreBookCount <= 0)) {
    return;
}
?>
<p class="game-detail__magazines-link game-detail__media-links">
    <?php if ($magazineIssueCount > 0): ?>
        <a href="<?= Moncine\View::escape($magazinesListUrl) ?>"
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
