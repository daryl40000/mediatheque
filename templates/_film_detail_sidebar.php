<?php
/**
 * Colonne gauche de la fiche film : jaquette, statut vu, actions rapides.
 *
 * @var array<string, mixed> $film
 * @var int $filmId
 * @var bool $isWishlist
 * @var bool $everSeen
 * @var string|null $derniereVue
 * @var string $popoverOpen note|edit|vu
 */
$posterSrc = Moncine\View::posterSrc($film['poster_url'] ?? null);
$isWishlist = $isWishlist ?? false;
$everSeen = $everSeen ?? false;
$popoverOpen = (string) ($popoverOpen ?? '');
?>
<aside class="game-detail-sidebar" aria-label="Jaquette et infos rapides">
    <?php if ($posterSrc !== ''): ?>
        <img class="film-poster film-poster--large game-detail-sidebar__poster" src="<?= $posterSrc ?>"
             alt="Affiche de <?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>">
    <?php else: ?>
        <span class="film-poster film-poster--large film-poster--empty game-detail-sidebar__poster" aria-hidden="true"></span>
    <?php endif; ?>

    <?php if (!$isWishlist && $everSeen): ?>
        <p class="game-detail-sidebar__finished">
            <span class="game-detail-sidebar__badge">Vu</span>
            <?php if (!empty($derniereVue)): ?>
                <span class="game-detail-sidebar__finished-date">
                    <?= Moncine\View::escape(Moncine\HistoriqueRepository::formatDateVue((string) $derniereVue)) ?>
                </span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!$isWishlist): ?>
        <?php require MONCINE_ROOT . '/templates/_film_detail_action_popovers.php'; ?>
    <?php endif; ?>
</aside>
