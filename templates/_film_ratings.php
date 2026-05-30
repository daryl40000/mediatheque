<?php
/**
 * Notes personnelle et moyenne du foyer pour un film.
 *
 * @var array<string, mixed> $film
 * @var bool $showFoyerAverage
 * @var string $layout inline|stacked
 */
$layout = $layout ?? 'stacked';
$showFoyerAverage = $showFoyerAverage ?? false;

$notePersoRaw = $film['note_max'] ?? null;
$notePersoInt = $notePersoRaw !== null && $notePersoRaw !== '' ? (int) $notePersoRaw : null;
$notePersoLabel = Moncine\HistoriqueRepository::formatNoteSur10($notePersoInt);

$noteFoyerRaw = $film['note_foyer_moy'] ?? null;
$noteFoyerLabel = Moncine\HistoriqueRepository::formatAverageNote(
    $noteFoyerRaw !== null && $noteFoyerRaw !== '' ? (float) $noteFoyerRaw : null
);

if ($notePersoLabel === '' && ($noteFoyerLabel === '' || !$showFoyerAverage)) {
    echo '—';
    return;
}
?>
<span class="film-ratings film-ratings--<?= Moncine\View::escape($layout) ?>">
    <?php if ($notePersoLabel !== ''): ?>
        <span class="film-ratings__item film-ratings__item--personal" title="Votre meilleure note">
            <?php if ($showFoyerAverage && $noteFoyerLabel !== ''): ?>
                <span class="film-ratings__label">Vous</span>
            <?php endif; ?>
            <span class="film-note film-note--small"><?= Moncine\View::escape($notePersoLabel) ?></span>
        </span>
    <?php endif; ?>
    <?php if ($showFoyerAverage && $noteFoyerLabel !== ''): ?>
        <span class="film-ratings__item film-ratings__item--foyer" title="Note moyenne du foyer">
            <span class="film-ratings__label">Foyer</span>
            <span class="film-note film-note--small film-note--foyer"><?= Moncine\View::escape($noteFoyerLabel) ?></span>
        </span>
    <?php endif; ?>
</span>
