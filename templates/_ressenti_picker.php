<?php
/**
 * Sélecteur de ressenti (5 paliers).
 *
 * @var string $fieldName nom du champ POST (défaut : ressenti)
 * @var string $fieldId identifiant HTML unique
 * @var int|null $defaultScore score 1–5 pré-sélectionné
 * @var bool $required au moins un choix obligatoire
 * @var bool $allowEmpty option « sans ressenti »
 */
$fieldName = $fieldName ?? 'ressenti';
$fieldId = $fieldId ?? 'ressenti_' . bin2hex(random_bytes(4));
$defaultScore = isset($defaultScore) ? Moncine\RessentiNote::normalizeScore((int) $defaultScore) : null;
$required = $required ?? false;
$allowEmpty = $allowEmpty ?? true;
?>
<fieldset class="ressenti-picker" id="<?= Moncine\View::escape($fieldId) ?>">
    <legend class="ressenti-picker__legend">Mon ressenti</legend>
    <div class="ressenti-picker__options" role="radiogroup" aria-labelledby="<?= Moncine\View::escape($fieldId) ?>">
        <?php if ($allowEmpty): ?>
            <label class="ressenti-picker__option">
                <input type="radio" name="<?= Moncine\View::escape($fieldName) ?>" value=""
                    <?= $defaultScore === null ? ' checked' : '' ?><?= $required ? '' : '' ?>>
                <span class="ressenti-picker__label">Sans ressenti</span>
            </label>
        <?php endif; ?>
        <?php foreach (Moncine\RessentiNote::orderedKeys() as $key):
            $score = Moncine\RessentiNote::score($key);
            $checked = $defaultScore === $score ? ' checked' : '';
            ?>
            <label class="ressenti-picker__option <?= Moncine\View::escape(Moncine\RessentiNote::cssClass($key)) ?>">
                <input type="radio" name="<?= Moncine\View::escape($fieldName) ?>" value="<?= $score ?>"<?= $checked ?><?= $required ? ' required' : '' ?>>
                <span class="ressenti-picker__icon" aria-hidden="true"><?= Moncine\RessentiNote::iconSvg($key) ?></span>
                <span class="ressenti-picker__label"><?= Moncine\View::escape(Moncine\RessentiNote::label($key)) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
