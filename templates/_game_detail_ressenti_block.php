<?php
/**
 * Formulaire d’ajout du ressenti (fiche jeu, lorsqu’aucun ressenti n’est encore enregistré).
 *
 * @var int $gameId
 * @var int|null $monRessenti
 * @var bool $isWishlist
 */
$gameId = (int) ($gameId ?? 0);
$isWishlist = $isWishlist ?? false;
$hasRessenti = !empty($monRessenti);

if ($isWishlist || $hasRessenti) {
    return;
}
?>
<section class="game-detail-ressenti game-detail-ressenti--add" aria-labelledby="game-ressenti-heading">
    <h2 id="game-ressenti-heading" class="visually-hidden">Mon ressenti</h2>
    <?php
    $defaultNote = null;
    require MONCINE_ROOT . '/templates/_marquer_joue_form.php';
    ?>
</section>
