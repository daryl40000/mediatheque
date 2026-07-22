<?php
/**
 * Jeux offerts avec le numéro — affichés à droite du sommaire.
 *
 * @var list<array<string, mixed>> $offeredSubjects
 * @var int $bibId
 */
$offeredSubjects = $offeredSubjects ?? [];
$bibId = (int) ($bibId ?? 0);
if ($offeredSubjects === []) {
    return;
}
?>
<aside class="magazine-jeux-offerts" aria-labelledby="magazine-jeux-offerts-heading">
    <h2 id="magazine-jeux-offerts-heading" class="game-detail__section-title">Jeux offerts</h2>
    <p class="hint magazine-jeux-offerts__hint">Jeux fournis avec ce numéro.</p>
    <?php
    // Variable dédiée : ne pas écraser $issueSubjects (ligne « Sujets et tests » plus bas).
    $stripSubjects = $offeredSubjects;
    require MONCINE_ROOT . '/templates/_magazine_issue_subjects_strip.php';
    unset($stripSubjects);
    ?>
</aside>
