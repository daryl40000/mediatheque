<?php
/**
 * Enregistrer une lecture (date uniquement).
 *
 * @var int $albumId
 * @var string $return album|home
 * @var string $defaultDateIso
 * @var string $submitLabel
 */
$defaultDateIso = $defaultDateIso ?? Moncine\HistoriqueRepository::todayForInputIso();
$submitLabel = $submitLabel ?? 'Marquer comme lu';
$maxDate = Moncine\HistoriqueRepository::todayForInputIso();
?>
<form method="post" action="/marquer-bd-lu.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="album_id" value="<?= (int) $albumId ?>">
    <input type="hidden" name="return" value="<?= Moncine\View::escape($return) ?>">

    <label for="date_lecture_<?= (int) $albumId ?>">Date de lecture</label>
    <div class="marquer-vu-form__row">
        <input type="date" name="date_vue" id="date_lecture_<?= (int) $albumId ?>"
               value="<?= Moncine\View::escape($defaultDateIso) ?>"
               max="<?= Moncine\View::escape($maxDate) ?>" required
               class="marquer-vu-form__date">
        <button type="button" class="btn btn-ghost btn-sm marquer-vu-today"
                data-target="date_lecture_<?= (int) $albumId ?>"
                data-today="<?= Moncine\View::escape($maxDate) ?>">
            Aujourd’hui
        </button>
    </div>
    <p class="hint">Aujourd’hui par défaut, ou une date passée si vous aviez oublié de noter.</p>

    <button type="submit" class="btn btn-secondary"><?= Moncine\View::escape($submitLabel) ?></button>
</form>
