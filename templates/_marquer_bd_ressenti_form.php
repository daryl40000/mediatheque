<?php
/**
 * Formulaire ressenti pour un album BD (sans date de lecture).
 *
 * @var int $albumId
 * @var int|null $defaultNote ressenti 1–5 déjà connu
 */
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
?>
<form method="post" action="/marquer-bd-ressenti.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="album_id" value="<?= (int) $albumId ?>">

    <?php
    $fieldName = 'note';
    $fieldId = 'note_bd_ressenti_' . (int) $albumId;
    $defaultScore = $defaultNote;
    $required = true;
    $allowEmpty = false;
    require MONCINE_ROOT . '/templates/_ressenti_picker.php';
    ?>

    <button type="submit" class="btn btn-secondary">Enregistrer mon ressenti</button>
</form>
