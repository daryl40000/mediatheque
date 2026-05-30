<?php
/**
 * Catégorie (film / série / spectacle…) + champs saison si série.
 *
 * @var array<string, mixed>|null $film
 * @var string $fieldPrefix
 */

$fieldPrefix = $fieldPrefix ?? 'edit';
$film = $film ?? null;

$currentFormValue = Moncine\MoncineContentKind::FILM;
if ($film !== null) {
    $fromPrefill = trim((string) ($film['content_kind'] ?? ''));
    $currentFormValue = $fromPrefill !== ''
        ? $fromPrefill
        : Moncine\MoncineContentKind::toFormValue(
            (string) ($film['moncine_kind'] ?? Moncine\MoncineContentKind::FILM),
            (string) ($film['tmdb_media_type'] ?? ''),
            (string) ($film['tmdb_tv_kind'] ?? '')
        );
}

$selectId = $fieldPrefix . '_content_kind';
$isSerie = $currentFormValue === Moncine\MoncineContentKind::SERIE
    || Moncine\MoncineContentKind::isSerie((string) ($film['moncine_kind'] ?? ''));
?>
<label for="<?= Moncine\View::escape($selectId) ?>">Catégorie</label>
<select name="content_kind" id="<?= Moncine\View::escape($selectId) ?>" class="js-content-kind-select">
    <?php foreach (Moncine\MoncineContentKind::formChoices() as $value => $label): ?>
        <option value="<?= Moncine\View::escape($value) ?>"<?= $currentFormValue === $value ? ' selected' : '' ?>>
            <?= Moncine\View::escape($label) ?>
        </option>
    <?php endforeach; ?>
</select>

<div class="js-serie-fields<?= $isSerie ? '' : ' is-hidden' ?>" id="<?= Moncine\View::escape($fieldPrefix) ?>_serie_fields">
    <label for="<?= Moncine\View::escape($fieldPrefix) ?>_saison_numero">N° de saison</label>
    <input type="number" name="saison_numero" id="<?= Moncine\View::escape($fieldPrefix) ?>_saison_numero"
           min="0" max="99" step="1" placeholder="1, 2, 3…"
           value="<?= (int) ($film['saison_numero'] ?? 0) > 0 ? (int) $film['saison_numero'] : '' ?>">

    <label for="<?= Moncine\View::escape($fieldPrefix) ?>_saison_label">Libellé saison (optionnel)</label>
    <input type="text" name="saison_label" id="<?= Moncine\View::escape($fieldPrefix) ?>_saison_label"
           placeholder="Saison 1, Intégrale…"
           value="<?= Moncine\View::escape((string) ($film['saison_label'] ?? '')) ?>">
    <p class="hint">Pour une série : indiquez la saison du coffret DVD/Blu-ray que vous possédez.</p>
</div>

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_ean">Code-barres (EAN)</label>
<input type="text" name="ean" id="<?= Moncine\View::escape($fieldPrefix) ?>_ean" inputmode="numeric"
       placeholder="ex. 3760061234567 (optionnel)"
       value="<?= Moncine\View::escape(Moncine\OeuvreEanRepository::normalizeEan((string) ($film['ean'] ?? ''))) ?>">
<p class="hint">Référence de votre édition DVD/Blu-ray. L’enrichissement des infos se fait via TMDB.</p>
