<?php
/**
 * Formulaire ressenti pour un jeu (sans date de session).
 *
 * @var int $gameId
 * @var int|null $defaultNote ressenti 1–5 déjà connu
 */
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
?>
<form method="post" action="/marquer-joue.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="game_id" value="<?= (int) $gameId ?>">

    <?php
    $fieldName = 'note';
    $fieldId = 'note_jeu_' . (int) $gameId;
    $defaultScore = $defaultNote;
    $required = true;
    $allowEmpty = false;
    require MONCINE_ROOT . '/templates/_ressenti_picker.php';
    ?>

    <button type="submit" class="btn btn-secondary">Enregistrer mon ressenti</button>
</form>
