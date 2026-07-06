<?php
/**
 * Formulaire ressenti pour un film (sans date de vision).
 *
 * @var int $filmId
 * @var int|null $defaultNote ressenti 1–5 déjà connu
 */
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
?>
<form method="post" action="/marquer-film-ressenti.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
    <?php if (isset($filmListContext)): ?>
        <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
    <?php endif; ?>

    <?php
    $fieldName = 'note';
    $fieldId = 'note_film_' . (int) $filmId;
    $defaultScore = $defaultNote;
    $required = true;
    $allowEmpty = false;
    require MONCINE_ROOT . '/templates/_ressenti_picker.php';
    ?>

    <button type="submit" class="btn btn-secondary">Enregistrer mon ressenti</button>
</form>
