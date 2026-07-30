<?php
/**
 * Formulaire ressenti pour un livre (sans date de lecture).
 *
 * @var int $bookId
 * @var int|null $defaultNote ressenti 1–5 déjà connu
 */
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
?>
<form method="post" action="/marquer-livre-ressenti.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="book_id" value="<?= (int) $bookId ?>">

    <?php
    $fieldName = 'note';
    $fieldId = 'note_livre_ressenti_' . (int) $bookId;
    $defaultScore = $defaultNote;
    $required = true;
    $allowEmpty = false;
    require MONCINE_ROOT . '/templates/_ressenti_picker.php';
    ?>

    <button type="submit" class="btn btn-secondary">Enregistrer mon ressenti</button>
</form>
