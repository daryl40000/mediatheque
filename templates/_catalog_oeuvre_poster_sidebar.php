<?php
/**
 * Colonne jaquette + actions sur une fiche catalogue.
 *
 * @var string $posterSrc
 * @var string $posterAlt
 * @var int $oeuvreId
 * @var bool $inLibrary
 * @var int|null $libraryBibId
 * @var string $mediaDomain
 * @var string $openLibraryLabel
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var int $profileUserId
 */
$libraryBibId = (int) ($libraryBibId ?? 0);
$inLibrary = !empty($inLibrary) && $libraryBibId > 0;
$libraryUrl = match (Moncine\MediaDomain::normalize($mediaDomain ?? Moncine\MediaDomain::FILM)) {
    Moncine\MediaDomain::JEU => Moncine\View::gameUrl($libraryBibId),
    Moncine\MediaDomain::MAGAZINE => Moncine\View::magazineIssueUrl($libraryBibId),
    Moncine\MediaDomain::BD => Moncine\View::bdUrl($libraryBibId),
    default => '/film.php?id=' . $libraryBibId,
};
$openLibraryLabel = trim((string) ($openLibraryLabel ?? 'Ouvrir ma fiche'));
?>
<aside class="game-detail-sidebar" aria-label="Jaquette et actions">
    <?php if (($posterSrc ?? '') !== ''): ?>
        <img class="film-poster film-poster--large game-detail-sidebar__poster" src="<?= $posterSrc ?>"
             alt="<?= Moncine\View::escape((string) ($posterAlt ?? '')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php if ($inLibrary): ?>
        <p>
            <a href="<?= Moncine\View::escape($libraryUrl) ?>" class="btn btn-primary btn-sm">
                <?= Moncine\View::escape($openLibraryLabel) ?>
            </a>
        </p>
    <?php else: ?>
        <?php require MONCINE_ROOT . '/templates/_catalog_oeuvre_sidebar_actions.php'; ?>
    <?php endif; ?>
</aside>
