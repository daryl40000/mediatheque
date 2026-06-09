<?php
/**
 * Saisie des genres jeu (badges réutilisables + suggestions du catalogue).
 *
 * @var list<string> $genreTagsList
 * @var list<string> $knownGenres
 */
$genreTagsList = $genreTagsList ?? [];
$knownGenres = $knownGenres ?? [];
?>
<div class="magazine-series-tags-field game-genre-tags-field" data-tags-badge-field data-tags-input-name="genres[]">
    <span id="game_genres_label" class="magazine-series-tags-field__label">Genres</span>

    <ul class="magazine-series-tags-field__list" role="list" aria-labelledby="game_genres_label">
        <?php foreach ($genreTagsList as $tag): ?>
            <?php $tag = trim((string) $tag); ?>
            <?php if ($tag === '') {
                continue;
            } ?>
            <li class="magazine-series-tags-field__item" role="listitem">
                <span class="magazine-tag magazine-tag--game-genre">
                    <?= Moncine\View::escape($tag) ?>
                    <button type="button"
                            class="magazine-series-tags-field__remove"
                            title="Retirer ce genre"
                            aria-label="Retirer le genre <?= Moncine\View::escape($tag) ?>">×</button>
                </span>
                <input type="hidden" name="genres[]" value="<?= Moncine\View::escape($tag) ?>">
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="magazine-series-tags-field__add">
        <label class="visually-hidden" for="game_genre_input">Nouveau genre</label>
        <input type="text"
               id="game_genre_input"
               class="magazine-series-tags-field__input"
               maxlength="80"
               autocomplete="off"
               list="game-genre-suggestions"
               placeholder="Ex. Action-RPG, FPS, Aventure…">
        <?php if ($knownGenres !== []): ?>
            <datalist id="game-genre-suggestions">
                <?php foreach ($knownGenres as $knownGenre): ?>
                    <option value="<?= Moncine\View::escape((string) $knownGenre) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary btn-sm magazine-series-tags-field__add-btn">Ajouter</button>
    </div>

    <p class="hint">
        Tapez un genre puis cliquez <strong>Ajouter</strong> (ou Entrée). Les genres déjà utilisés sur d’autres jeux
        sont proposés dans la liste : vous pouvez les réutiliser pour garder des libellés cohérents.
    </p>
</div>
