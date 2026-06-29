<?php
/**
 * Cases à cocher plateformes (catalogue ou exemplaire utilisateur).
 *
 * @var string $platformFieldName ex. platforms[] ou owned_platforms[]
 * @var list<string> $selectedPlatformKeys
 * @var array<string, string> $platformChoices
 * @var string $legend
 * @var string $hint
 * @var list<string>|null $allowedPlatformKeys null = toutes les plateformes actives
 * @var bool $hidden
 */
$platformFieldName = (string) ($platformFieldName ?? 'platforms[]');
$selectedPlatformKeys = $selectedPlatformKeys ?? [];
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$legend = (string) ($legend ?? 'Plateformes');
$hint = (string) ($hint ?? '');
$allowedPlatformKeys = $allowedPlatformKeys ?? null;
$hidden = !empty($hidden);

if ($allowedPlatformKeys !== null) {
    $allowed = [];
    foreach ($allowedPlatformKeys as $key) {
        $key = Moncine\GamePlatform::normalize((string) $key);
        if ($key !== '' && isset($platformChoices[$key])) {
            $allowed[$key] = $platformChoices[$key];
        }
    }
    $platformChoices = $allowed;
}
$fieldsetExtraAttrs = (string) ($fieldsetExtraAttrs ?? '');
?>
<fieldset class="game-platform-fieldset" data-game-platform-fieldset<?= $hidden ? ' hidden' : '' ?><?= $fieldsetExtraAttrs !== '' ? ' ' . $fieldsetExtraAttrs : '' ?>>
    <legend><?= Moncine\View::escape($legend) ?></legend>
    <?php if ($hint !== ''): ?>
        <p class="hint"><?= Moncine\View::escape($hint) ?></p>
    <?php endif; ?>
    <?php if ($platformChoices === []): ?>
        <p class="hint">Aucune plateforme disponible. Un administrateur peut en ajouter dans la maintenance jeux.</p>
    <?php else: ?>
        <div class="game-platform-checkboxes" data-game-platform-checkboxes data-field-name="<?= Moncine\View::escape($platformFieldName) ?>">
            <?php foreach ($platformChoices as $key => $label): ?>
                <label class="checkbox-inline game-platform-checkboxes__item">
                    <input type="checkbox" name="<?= Moncine\View::escape($platformFieldName) ?>" value="<?= Moncine\View::escape($key) ?>"
                        data-game-platform-key="<?= Moncine\View::escape($key) ?>"
                        <?= in_array($key, $selectedPlatformKeys, true) ? ' checked' : '' ?>>
                    <?= Moncine\View::escape($label) ?>
                    <span class="hint">(<?= Moncine\View::escape(Moncine\GamePlatform::shortLabel($key)) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</fieldset>
