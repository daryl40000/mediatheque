<?php
/**
 * Enregistrer une lecture (date + ressenti optionnel).
 *
 * @var int $albumId
 * @var string $return album|home
 * @var string $defaultDateIso
 * @var string $submitLabel
 * @var int|null $defaultNote ressenti 1–5
 */
$defaultDateIso = $defaultDateIso ?? Moncine\HistoriqueRepository::todayForInputIso();
$submitLabel = $submitLabel ?? 'Marquer comme lu';
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
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

    <?php
    $fieldName = 'note';
    $fieldId = 'note_bd_' . (int) $albumId;
    $defaultScore = $defaultNote;
    $required = false;
    $allowEmpty = true;
    require MONCINE_ROOT . '/templates/_ressenti_picker.php';
    ?>

    <button type="submit" class="btn btn-secondary"><?= Moncine\View::escape($submitLabel) ?></button>
</form>
