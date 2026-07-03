<?php
/**
 * Enregistrer une vision (date du jour ou passée).
 *
 * @var int $filmId
 * @var string $return film|resultat|home
 * @var string $defaultDateIso aaaa-mm-jj pour le champ date
 * @var string $submitLabel
 * @var int|null $defaultNote ressenti 1–5 déjà connu (pré-sélection)
 * @var bool $compact affichage réduit (page résultat)
 */
$defaultDateIso = $defaultDateIso ?? Moncine\HistoriqueRepository::todayForInputIso();
$submitLabel = $submitLabel ?? 'Marquer comme vu';
$compact = $compact ?? false;
$defaultNote = isset($defaultNote) ? Moncine\RessentiNote::normalizeScore((int) $defaultNote) : null;
$maxDate = Moncine\HistoriqueRepository::todayForInputIso();
?>
<form method="post" action="/marquer-vu.php" class="marquer-vu-form import-form<?= $compact ? ' marquer-vu-form--compact' : '' ?>">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
    <input type="hidden" name="return" value="<?= Moncine\View::escape($return) ?>">
    <?php if ($return === 'film' && isset($filmListContext)): ?>
        <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
    <?php endif; ?>

    <label for="date_vue_<?= (int) $filmId ?>">Date de vision</label>
    <div class="marquer-vu-form__row">
        <input type="date" name="date_vue" id="date_vue_<?= (int) $filmId ?>"
               value="<?= Moncine\View::escape($defaultDateIso) ?>"
               max="<?= Moncine\View::escape($maxDate) ?>" required
               class="marquer-vu-form__date">
        <button type="button" class="btn btn-ghost btn-sm marquer-vu-today"
                data-target="date_vue_<?= (int) $filmId ?>"
                data-today="<?= Moncine\View::escape($maxDate) ?>">
            Aujourd’hui
        </button>
    </div>
    <?php if (!$compact): ?>
        <p class="hint">Utilisez le calendrier : aujourd’hui par défaut, ou une date passée si vous aviez oublié de noter.</p>
    <?php endif; ?>

    <?php if (!$compact): ?>
        <?php
        $fieldName = 'note';
        $fieldId = 'note_' . (int) $filmId;
        $defaultScore = $defaultNote;
        $required = false;
        $allowEmpty = true;
        require MONCINE_ROOT . '/templates/_ressenti_picker.php';
        ?>
    <?php endif; ?>

    <button type="submit" class="btn <?= $compact ? 'btn-primary' : 'btn-secondary' ?>"><?= Moncine\View::escape($submitLabel) ?></button>
</form>
