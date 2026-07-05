<?php
/**
 * Fusion manuelle de deux fiches catalogue (admin).
 *
 * @var int $currentOeuvreId
 * @var string $currentOeuvreTitle
 * @var string $mediaDomain
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 * @var string $mergeMessage
 * @var string $mergeError
 */
$currentOeuvreId = (int) ($currentOeuvreId ?? 0);
$currentOeuvreTitle = trim((string) ($currentOeuvreTitle ?? ''));
$mediaDomain = Moncine\MediaDomain::normalize((string) ($mediaDomain ?? Moncine\MediaDomain::FILM));
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = max(1, (int) ($catalogPage ?? 1));
$mergeMessage = trim((string) ($mergeMessage ?? ''));
$mergeError = trim((string) ($mergeError ?? ''));

if ($currentOeuvreId <= 0) {
    return;
}

$catalogMergeSearchUrl = match ($mediaDomain) {
    Moncine\MediaDomain::JEU => '/rechercher-jeux-catalogue.php',
    Moncine\MediaDomain::FILM => '/rechercher-oeuvres.php',
    default => '/rechercher-oeuvres-catalogue.php?domain=' . rawurlencode($mediaDomain),
};

$mediaLabel = Moncine\MediaDomain::label($mediaDomain);
?>
<details class="catalog-admin-panel catalog-oeuvre-merge-panel" data-catalog-oeuvre-merge
         data-current-oeuvre-id="<?= $currentOeuvreId ?>"
         data-catalog-search-url="<?= Moncine\View::escape($catalogMergeSearchUrl) ?>">
    <summary class="catalog-admin-panel__summary">Fusionner avec une autre fiche</summary>
    <div class="catalog-admin-panel__body">
        <?php if ($mergeMessage !== ''): ?>
            <p class="alert alert-success"><?= Moncine\View::escape($mergeMessage) ?></p>
        <?php endif; ?>
        <?php if ($mergeError !== ''): ?>
            <p class="alert alert-warning"><?= Moncine\View::escape($mergeError) ?></p>
        <?php endif; ?>

        <p class="hint">
            Regroupez deux doublons du catalogue <?= Moncine\View::escape(strtolower($mediaLabel)) ?> :
            les entrées de bibliothèque et l’historique de la fiche supprimée sont transférés
            vers la fiche conservée. Action irréversible.
        </p>

        <form method="post" action="/fusionner-oeuvre-catalogue.php" class="catalog-oeuvre-merge-form import-form"
              data-catalog-oeuvre-merge-form>
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="current_oeuvre_id" value="<?= $currentOeuvreId ?>">
            <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
            <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
            <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
            <input type="hidden" name="catalog_page" value="<?= $catalogPage ?>">
            <input type="hidden" name="keep_id" value="<?= $currentOeuvreId ?>" data-catalog-merge-keep-id>
            <input type="hidden" name="remove_id" value="" data-catalog-merge-remove-id>
            <input type="hidden" name="other_oeuvre_id" value="" data-catalog-merge-other-id>

            <fieldset class="catalog-oeuvre-merge-form__direction">
                <legend class="visually-hidden">Sens de la fusion</legend>
                <label class="catalog-oeuvre-merge-form__choice">
                    <input type="radio" name="merge_direction" value="keep_current" checked
                           data-catalog-merge-direction>
                    Conserver <strong>cette fiche</strong>
                    <?php if ($currentOeuvreTitle !== ''): ?>
                        (« <?= Moncine\View::escape($currentOeuvreTitle) ?> »)
                    <?php endif; ?>
                    et fusionner l’autre dedans
                </label>
                <label class="catalog-oeuvre-merge-form__choice">
                    <input type="radio" name="merge_direction" value="keep_other"
                           data-catalog-merge-direction>
                    Fusionner cette fiche dans une autre fiche (conserver l’autre)
                </label>
            </fieldset>

            <label for="catalog_merge_search_<?= $currentOeuvreId ?>">Autre fiche à fusionner</label>
            <div class="catalog-title-autocomplete" data-catalog-merge-autocomplete>
                <input type="search" id="catalog_merge_search_<?= $currentOeuvreId ?>"
                       data-catalog-merge-search autocomplete="off"
                       placeholder="Rechercher par titre…" required>
                <div class="catalog-title-autocomplete__list" data-catalog-merge-list hidden role="listbox"></div>
            </div>
            <p class="hint catalog-oeuvre-merge-form__selected" data-catalog-merge-hint hidden></p>

            <button type="submit" class="btn btn-danger" data-catalog-merge-submit>
                Fusionner les fiches
            </button>
        </form>
    </div>
</details>
