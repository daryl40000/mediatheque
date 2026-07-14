<?php
/**
 * Saisie des catégories de série magazine (badges + suggestions).
 *
 * @var list<string> $seriesCategoriesList
 * @var list<string> $knownCategories
 */
$seriesCategoriesList = $seriesCategoriesList ?? [];
$knownCategories = $knownCategories ?? Moncine\MagazineSeriesCategory::suggestionLabels();
?>
<div class="magazine-series-tags-field magazine-series-categories-field" data-tags-badge-field data-tags-input-name="categories[]">
    <span id="series_categories_label" class="magazine-series-tags-field__label">Catégories de la série</span>

    <ul class="magazine-series-tags-field__list" role="list" aria-labelledby="series_categories_label">
        <?php foreach ($seriesCategoriesList as $category): ?>
            <?php $category = trim((string) $category); ?>
            <?php if ($category === '') {
                continue;
            } ?>
            <li class="magazine-series-tags-field__item" role="listitem">
                <span class="magazine-tag magazine-tag--series-category">
                    <?= Moncine\View::escape($category) ?>
                    <button type="button"
                            class="magazine-series-tags-field__remove"
                            title="Retirer cette catégorie"
                            aria-label="Retirer la catégorie <?= Moncine\View::escape($category) ?>">×</button>
                </span>
                <input type="hidden" name="categories[]" value="<?= Moncine\View::escape($category) ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="magazine-series-tags-field__add">
        <label class="visually-hidden" for="series_category_input">Nouvelle catégorie</label>
        <input type="text"
               id="series_category_input"
               class="magazine-series-tags-field__input"
               maxlength="80"
               autocomplete="off"
               list="magazine-series-category-suggestions"
               placeholder="Ex. Jeux vidéo, Cinéma, Figurines…">
        <?php if ($knownCategories !== []): ?>
            <datalist id="magazine-series-category-suggestions">
                <?php foreach ($knownCategories as $knownCategory): ?>
                    <option value="<?= Moncine\View::escape((string) $knownCategory) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm magazine-series-tags-field__add-btn">Ajouter</button>
    </div>

    <p class="hint">
        Choisissez une ou plusieurs catégories thématiques pour cette revue
        (ex. <strong>Jeux vidéo</strong>, <strong>Cinéma</strong>).
        Elles s’affichent automatiquement sur <strong>tous les numéros</strong> de la série.
        Tapez puis cliquez <strong>Ajouter</strong> (ou Entrée) — les catégories déjà utilisées
        sur d’autres revues sont proposées dans la liste.
    </p>
</div>
