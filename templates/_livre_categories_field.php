<?php
/**
 * Saisie des catégories de livre (badges + suggestions).
 *
 * @var list<string> $livreCategoriesList
 * @var list<string> $knownCategories
 */
$livreCategoriesList = $livreCategoriesList ?? [];
$knownCategories = $knownCategories ?? Moncine\LivreCategory::suggestionLabels();
?>
<div class="magazine-series-tags-field magazine-series-categories-field livre-categories-field"
     data-tags-badge-field
     data-tags-input-name="categories[]"
     data-livre-categories-field>
    <span id="livre_categories_label" class="magazine-series-tags-field__label">Catégories</span>

    <ul class="magazine-series-tags-field__list" role="list" aria-labelledby="livre_categories_label">
        <?php foreach ($livreCategoriesList as $category): ?>
            <?php $category = trim((string) $category); ?>
            <?php if ($category === '') {
                continue;
            } ?>
            <li class="magazine-series-tags-field__item" role="listitem">
                <span class="magazine-tag magazine-tag--series-category">
                    <span class="magazine-series-tags-field__text"><?= Moncine\View::escape($category) ?></span>
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
        <label class="visually-hidden" for="livre_category_input">Nouvelle catégorie</label>
        <input type="text"
               id="livre_category_input"
               class="magazine-series-tags-field__input"
               maxlength="80"
               autocomplete="off"
               list="livre-category-suggestions"
               placeholder="Ex. Jeux vidéo, Cinéma, Figurines…">
        <?php if ($knownCategories !== []): ?>
            <datalist id="livre-category-suggestions">
                <?php foreach ($knownCategories as $knownCategory): ?>
                    <option value="<?= Moncine\View::escape((string) $knownCategory) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm magazine-series-tags-field__add-btn">Ajouter</button>
    </div>

    <p class="hint">
        Choisissez une ou plusieurs catégories (ex. <strong>Jeux vidéo</strong>, <strong>Cinéma</strong>).
        Avec <strong>Jeux vidéo</strong>, vous pourrez lier les jeux dont parle le livre.
    </p>
</div>
