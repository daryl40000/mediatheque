<?php
/**
 * Formulaire « J’ai terminé ce jeu » (avec date).
 *
 * @var int $gameId
 * @var string $defaultDateIso aaaa-mm-jj pour le champ date
 * @var string $submitLabel
 */
$defaultDateIso = $defaultDateIso ?? Moncine\HistoriqueRepository::todayForInputIso();
$submitLabel = $submitLabel ?? 'J’ai terminé ce jeu';
$maxDate = Moncine\HistoriqueRepository::todayForInputIso();
?>
<form method="post" action="/marquer-jeu-fini.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="game_id" value="<?= (int) $gameId ?>">

    <label for="date_fin_<?= (int) $gameId ?>">Date de fin</label>
    <div class="marquer-vu-form__row">
        <input type="date" name="date_fin" id="date_fin_<?= (int) $gameId ?>"
               value="<?= Moncine\View::escape($defaultDateIso) ?>"
               max="<?= Moncine\View::escape($maxDate) ?>" required
               class="marquer-vu-form__date">
        <button type="button" class="btn btn-ghost btn-sm marquer-vu-today"
                data-target="date_fin_<?= (int) $gameId ?>"
                data-today="<?= Moncine\View::escape($maxDate) ?>">
            Aujourd’hui
        </button>
    </div>
    <p class="hint">Par défaut aujourd’hui ; vous pouvez choisir une date passée si vous aviez oublié de noter.</p>

    <button type="submit" class="btn btn-secondary"><?= Moncine\View::escape($submitLabel) ?></button>
</form>
