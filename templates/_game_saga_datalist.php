<?php
/**
 * Suggestions HTML5 pour le champ saga (franchise IGDB).
 *
 * @var list<string> $knownSagas
 * @var string $datalistId
 */
$knownSagas = $knownSagas ?? [];
$datalistId = trim((string) ($datalistId ?? 'game-saga-suggestions'));
if ($knownSagas === [] || $datalistId === '') {
    return;
}
?>
<datalist id="<?= Moncine\View::escape($datalistId) ?>">
    <?php foreach ($knownSagas as $sagaHint): ?>
        <option value="<?= Moncine\View::escape((string) $sagaHint) ?>"></option>
    <?php endforeach; ?>
</datalist>
