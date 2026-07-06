<?php
/**
 * Saisie du temps de jeu manuel (Battle.net, etc.).
 *
 * @var array<string, mixed> $gameRow
 */
if (!Moncine\GameSchema::hasManualPlaytimeColumn()) {
    return;
}

$manualMinutes = (int) ($gameRow['manual_playtime_minutes'] ?? 0);
$manualParts = Moncine\GamePlaytime::splitMinutes($manualMinutes);
$steamMinutes = (int) ($gameRow['steam_playtime_minutes'] ?? 0);
?>
<fieldset class="game-playtime-fieldset">
    <legend>Temps de jeu manuel</legend>
    <p class="hint">
        Pour les jeux <strong>Battle.net</strong>, <strong>Epic</strong> ou autres plateformes sans synchronisation automatique.
        Saisissez le temps affiché en jeu (commande <code>/played</code> sur WoW, profil Battle.net, etc.).
        <?php if ($steamMinutes > 0): ?>
            Le temps <strong>Steam</strong> (<?= Moncine\View::escape(Moncine\GamePlaytime::format($steamMinutes)) ?>) est ajouté séparément.
        <?php endif; ?>
    </p>

    <div class="game-playtime-fieldset__inputs">
        <label for="manual_playtime_hours">Heures</label>
        <input type="number" name="manual_playtime_hours" id="manual_playtime_hours"
               min="0" max="99999" step="1"
               value="<?= (int) $manualParts['hours'] ?>"
               placeholder="0">

        <label for="manual_playtime_minutes_part">Minutes</label>
        <input type="number" name="manual_playtime_minutes_part" id="manual_playtime_minutes_part"
               min="0" max="59" step="1"
               value="<?= (int) $manualParts['minutes'] ?>"
               placeholder="0">
    </div>
    <p class="hint">Laissez 0 h et 0 min pour effacer un temps manuel précédent.</p>
</fieldset>
