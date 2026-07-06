<?php
/**
 * Formulaire de correction de l’exemplaire personnel (support, format, saga…).
 *
 * @var array<string, mixed> $film
 * @var int $filmId
 * @var bool $editOpen
 * @var string $saveError
 * @var bool $canManageCatalog
 */
$editOpen = $editOpen ?? false;
$saveError = $saveError ?? '';
$canManageCatalog = $canManageCatalog ?? false;
$embedInPopover = $embedInPopover ?? false;
$moncineKind = (string) ($film['moncine_kind'] ?? Moncine\MoncineContentKind::FILM);
$oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
if (!$embedInPopover):
    ?>
<details class="film-edit-panel"<?= $editOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Modifier mon exemplaire</summary>
<?php endif; ?>

    <p class="hint">
        Ces champs concernent <strong>votre</strong> DVD, Blu-ray ou autre support
        (format image, bande sonore, saga personnelle, code-barres…).
        Le titre, le synopsis et l’affiche font partie du catalogue partagé.
    </p>

    <?php if ($canManageCatalog && $oeuvreId > 0): ?>
        <p class="hint">
            <a href="<?= Moncine\View::escape(Moncine\View::oeuvreUrl($oeuvreId)) ?>">
                Modifier la fiche catalogue (admin)
            </a>
        </p>
    <?php elseif (!$canManageCatalog): ?>
        <p class="hint">
            Pour corriger le titre, le synopsis ou l’affiche, contactez l’administrateur du site.
        </p>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/modifier-film.php" class="film-edit-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="film_id" value="<?= (int) $filmId ?>">
        <?php if (isset($filmListContext)): ?>
            <?php require MONCINE_ROOT . '/templates/_film_list_context_fields.php'; ?>
        <?php endif; ?>
        <input type="hidden" name="content_kind" value="<?= Moncine\View::escape(
            Moncine\MoncineContentKind::toFormValue(
                $moncineKind,
                (string) ($film['tmdb_media_type'] ?? ''),
                (string) ($film['tmdb_tv_kind'] ?? '')
            )
        ) ?>">

        <fieldset>
            <legend>Mon exemplaire</legend>

            <label for="edit_format_image">Format image</label>
            <input type="text" name="format_image" id="edit_format_image"
                   placeholder="Blu-ray, DVD, 4K UHD…"
                   value="<?= Moncine\View::escape((string) ($film['format_image'] ?? '')) ?>">

            <label for="edit_format_son">Bande sonore</label>
            <input type="text" name="format_son" id="edit_format_son"
                   placeholder="VF, VOST, VFQ…"
                   value="<?= Moncine\View::escape((string) ($film['format_son'] ?? '')) ?>">

            <label for="edit_support_physique">Support physique</label>
            <select name="support_physique" id="edit_support_physique">
                <option value="">— Non renseigné —</option>
                <?php
                $currentSupport = (string) ($film['support_physique'] ?? '');
                foreach (Moncine\SupportPhysique::choices() as $key => $label):
                    $sel = $currentSupport === $key ? ' selected' : '';
                    ?>
                    <option value="<?= Moncine\View::escape($key) ?>"<?= $sel ?>><?= Moncine\View::escape($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="edit_saga">Saga (votre classement)</label>
            <input type="text" name="saga" id="edit_saga" list="edit_saga_list"
                   placeholder="ex. Jason Bourne (laisser vide pour retirer)"
                   value="<?= Moncine\View::escape((string) ($film['saga'] ?? '')) ?>">
            <?php if (!empty($sagaSuggestions)): ?>
                <datalist id="edit_saga_list">
                    <?php foreach ($sagaSuggestions as $sagaHint): ?>
                        <option value="<?= Moncine\View::escape($sagaHint) ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>

            <label for="edit_saga_ordre">N° dans la saga</label>
            <input type="number" name="saga_ordre" id="edit_saga_ordre" min="1" max="999" step="1"
                   placeholder="1, 2, 3…"
                   value="<?= (int) ($film['saga_ordre'] ?? 0) > 0 ? (int) $film['saga_ordre'] : '' ?>">

            <?php if ($moncineKind === Moncine\MoncineContentKind::SERIE): ?>
                <label for="edit_saison_numero">Saison n°</label>
                <input type="number" name="saison_numero" id="edit_saison_numero" min="0" max="99"
                       value="<?= (int) ($film['saison_numero'] ?? 0) > 0 ? (int) $film['saison_numero'] : '' ?>">

                <label for="edit_saison_label">Libellé saison</label>
                <input type="text" name="saison_label" id="edit_saison_label"
                       placeholder="Saison 1, Intégrale…"
                       value="<?= Moncine\View::escape((string) ($film['saison_label'] ?? '')) ?>">
            <?php endif; ?>

            <label for="edit_ean">Code-barres (EAN)</label>
            <?php if (!empty($catalogEanSuggestion)): ?>
                <p class="hint" id="catalog_ean_hint">
                    EAN catalogue pour ce support :
                    <code><?= Moncine\View::escape(Moncine\View::formatEan((string) $catalogEanSuggestion)) ?></code>
                    — vous pouvez le recopier si votre exemplaire correspond.
                </p>
            <?php endif; ?>
            <input type="text" name="ean" id="edit_ean" inputmode="numeric"
                   placeholder="13 chiffres"
                   value="<?= Moncine\View::escape(Moncine\OeuvreEanRepository::normalizeEan((string) ($film['ean'] ?? ''))) ?>">
        </fieldset>

        <button type="submit" class="btn btn-primary">Enregistrer mon exemplaire</button>
    </form>
<?php if (!$embedInPopover): ?>
</details>
<?php endif; ?>
