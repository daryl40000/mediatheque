<?php
/**
 * Colonne gauche de la fiche livre : couvertures, badge Lu, actions rapides.
 *
 * @var array<string, mixed> $book
 * @var int $bookId
 * @var bool $isWishlist
 * @var bool $everRead
 * @var string $readAtLabel
 * @var string $popoverOpen note|edit|lu
 * @var int|null $monRessenti
 * @var string $editError
 * @var list<array<string, mixed>> $readHistory
 * @var list<array<string, mixed>> $linkedGames
 * @var list<string> $knownCategories
 * @var list<string> $sagaSuggestions
 */
$posterSrc = Moncine\View::posterSrc($book['poster_url'] ?? null);
$backCoverSrc = Moncine\View::posterSrc($book['back_cover_url'] ?? null);
$titre = (string) ($book['display_titre'] ?? $book['titre'] ?? 'Livre');
$isWishlist = $isWishlist ?? false;
$everRead = $everRead ?? false;
$readAtLabel = trim((string) ($readAtLabel ?? ''));
$popoverOpen = (string) ($popoverOpen ?? '');
?>
<aside class="game-detail-sidebar livre-detail-sidebar" aria-label="Couvertures et infos rapides">
    <?php if ($posterSrc !== ''): ?>
        <img class="film-poster film-poster--large game-detail-sidebar__poster"
             src="<?= $posterSrc ?>"
             alt="Couverture de <?= Moncine\View::escape($titre) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
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
        <?php require MONCINE_ROOT . '/templates/_livre_detail_action_popovers.php'; ?>
    <?php endif; ?>

    <?php if ($backCoverSrc !== ''): ?>
        <figure class="livre-back-cover">
            <button type="button"
                    class="livre-back-cover__open"
                    data-livre-cover-lightbox
                    data-cover-src="<?= $backCoverSrc ?>"
                    data-cover-alt="4e de couverture de <?= Moncine\View::escape($titre) ?>"
                    aria-label="Agrandir la 4e de couverture">
                <img class="livre-back-cover__img"
                     src="<?= $backCoverSrc ?>"
                     alt="4e de couverture de <?= Moncine\View::escape($titre) ?>">
            </button>
            <figcaption class="hint livre-back-cover__caption">4e de couverture — cliquer pour agrandir</figcaption>
        </figure>
    <?php endif; ?>
</aside>
