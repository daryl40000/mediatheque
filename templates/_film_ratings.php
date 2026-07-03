<?php
/**
 * Notes personnelle (ressenti) pour un film en liste.
 *
 * @var array<string, mixed> $film
 * @var string $layout inline|stacked
 */
$layout = $layout ?? 'stacked';

$notePersoRaw = $film['note_max'] ?? null;
$notePersoInt = $notePersoRaw !== null && $notePersoRaw !== '' ? (int) $notePersoRaw : null;

if ($notePersoInt === null || $notePersoInt < 1) {
    echo '—';
    return;
}
?>
<span class="film-ratings film-ratings--<?= Moncine\View::escape($layout) ?>">
    <span class="film-ratings__item film-ratings__item--personal" title="Votre ressenti">
        <?php
        $score = $notePersoInt;
        $showLabel = false;
        $size = 'small';
        require MONCINE_ROOT . '/templates/_ressenti_badge.php';
        ?>
    </span>
</span>
