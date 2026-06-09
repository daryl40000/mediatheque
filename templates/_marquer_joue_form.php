<?php
/**
 * Formulaire note sur 10 pour un jeu (sans date de session).
 *
 * @var int $gameId
 * @var int|null $defaultNote note 1–10 déjà connue (pré-sélection)
 */
$defaultNote = isset($defaultNote) ? (int) $defaultNote : null;
if ($defaultNote !== null && ($defaultNote < 1 || $defaultNote > 10)) {
    $defaultNote = null;
}
?>
<form method="post" action="/marquer-joue.php" class="marquer-vu-form import-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="game_id" value="<?= (int) $gameId ?>">

    <label for="note_jeu_<?= (int) $gameId ?>">Votre note sur 10</label>
    <select name="note" id="note_jeu_<?= (int) $gameId ?>" class="marquer-vu-form__note" required>
        <option value="" disabled<?= $defaultNote === null ? ' selected' : '' ?>>Choisir une note</option>
        <?php for ($n = 10; $n >= 1; $n--):
            $sel = $defaultNote === $n ? ' selected' : '';
            ?>
            <option value="<?= $n ?>"<?= $sel ?>><?= $n ?>/10</option>
        <?php endfor; ?>
    </select>

    <button type="submit" class="btn btn-secondary">Enregistrer la note</button>
</form>
