<?php
/**
 * Saisie des tags de série magazine (badges + ajout un par un).
 *
 * @var list<string> $seriesTagsList
 */
$seriesTagsList = $seriesTagsList ?? [];
?>
<div class="magazine-series-tags-field" data-series-tags-field>
    <span id="series_tags_label" class="magazine-series-tags-field__label">Tags de la série</span>

    <ul class="magazine-series-tags-field__list" role="list" aria-labelledby="series_tags_label">
        <?php foreach ($seriesTagsList as $tag): ?>
            <?php $tag = trim((string) $tag); ?>
            <?php if ($tag === '') {
                continue;
            } ?>
            <li class="magazine-series-tags-field__item" role="listitem">
                <span class="magazine-tag magazine-tag--series">
                    <?= Moncine\View::escape($tag) ?>
                    <button type="button"
                            class="magazine-series-tags-field__remove"
                            title="Retirer ce tag"
                            aria-label="Retirer le tag <?= Moncine\View::escape($tag) ?>">×</button>
                </span>
                <input type="hidden" name="tags[]" value="<?= Moncine\View::escape($tag) ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="magazine-series-tags-field__add">
        <label class="visually-hidden" for="series_tag_input">Nouveau tag</label>
        <input type="text"
               id="series_tag_input"
               class="magazine-series-tags-field__input"
               maxlength="80"
               autocomplete="off"
               placeholder="Ex. PC, PS5, Diesel…">
        <button type="button" class="btn btn-secondary btn-sm magazine-series-tags-field__add-btn">Ajouter</button>
    </div>

    <p class="hint">
        Tapez un tag puis cliquez <strong>Ajouter</strong> (ou Entrée). Répétez pour en mettre plusieurs.
        <strong>Un seul tag</strong> s’applique automatiquement à tous les sujets de la revue ;
        <strong>plusieurs tags</strong> apparaissent en menu déroulant sur chaque numéro.
    </p>
</div>
