<?php
/**
 * Champs formulaire admin catalogue — jeu vidéo (sans bibliothèque).
 *
 * @var array<string, mixed>|null $game
 * @var string $fieldPrefix
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 */
$game = $game ?? null;
$fieldPrefix = $fieldPrefix ?? 'add_game';
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$knownGenres = $knownGenres ?? [];

$gameRow = is_array($game) ? $game : [];
$selectedPlatform = (string) ($gameRow['platform'] ?? '');
$isExtension = !empty($gameRow['is_extension']);
$baseGameOeuvreId = (int) ($gameRow['base_game_oeuvre_id'] ?? 0);
$baseGameLabel = trim((string) ($gameRow['base_game_label'] ?? ''));
?>
<label for="<?= Moncine\View::escape($fieldPrefix) ?>_titre">Titre du jeu <span class="required">*</span></label>
<input type="hidden" name="oeuvre_id" id="<?= Moncine\View::escape($fieldPrefix) ?>_oeuvre_id" value="">
<div class="catalog-title-autocomplete" id="catalog-game-title-autocomplete"
     data-game-catalog-autocomplete
     data-search-url="/rechercher-jeux-catalogue.php"
     data-oeuvre-id-input="<?= Moncine\View::escape($fieldPrefix) ?>_oeuvre_id"
     data-annee-input="<?= Moncine\View::escape($fieldPrefix) ?>_annee"
     data-studio-input="<?= Moncine\View::escape($fieldPrefix) ?>_studio"
     data-platform-input="<?= Moncine\View::escape($fieldPrefix) ?>_platform">
    <input type="text" name="titre" id="<?= Moncine\View::escape($fieldPrefix) ?>_titre" required
           class="catalog-title-autocomplete__input"
           autocomplete="off" autocapitalize="off" spellcheck="false"
           placeholder="Tapez le titre — ne choisissez pas un jeu déjà listé"
           aria-autocomplete="list" aria-controls="catalog-game-title-suggestions"
           aria-expanded="false">
    <ul class="catalog-title-autocomplete__list" id="catalog-game-title-suggestions"
        role="listbox" hidden></ul>
</div>

<?php if (Moncine\GameRepository::hasExtensionColumns()): ?>
    <fieldset class="game-extension-fieldset" data-game-extension-root>
        <legend>Type de fiche</legend>

        <label class="checkbox-inline">
            <input type="checkbox" name="is_extension" value="1" data-game-extension-toggle
                <?= $isExtension ? ' checked' : '' ?>>
            Cette fiche est une extension (DLC / add-on)
        </label>

        <div class="game-extension-panel" data-game-extension-panel<?= $isExtension ? '' : ' hidden' ?>>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_base_game_query">Jeu de base (catalogue) <span class="required">*</span></label>
            <div class="catalog-title-autocomplete">
                <input type="search" id="<?= Moncine\View::escape($fieldPrefix) ?>_base_game_query" name="base_game_query" maxlength="200"
                       value="<?= Moncine\View::escape($baseGameLabel) ?>"
                       placeholder="Tapez le titre du jeu…"
                       autocomplete="off"
                       class="catalog-title-autocomplete__input"
                       data-game-extension-search>
                <div class="catalog-title-autocomplete__list" role="listbox" hidden data-game-extension-list></div>
            </div>
            <input type="hidden" name="base_game_oeuvre_id" id="<?= Moncine\View::escape($fieldPrefix) ?>_base_game_oeuvre_id"
                   value="<?= $baseGameOeuvreId > 0 ? $baseGameOeuvreId : '' ?>"
                   data-game-extension-oeuvre-id>

            <p class="hint" id="base_game_hint"<?= $baseGameLabel !== '' ? '' : ' hidden' ?>>
                Jeu sélectionné : <strong id="base_game_hint_label"><?= Moncine\View::escape($baseGameLabel) ?></strong>
                <button type="button" class="btn btn-sm btn-secondary" data-game-extension-clear>Effacer</button>
            </p>
        </div>
    </fieldset>
<?php endif; ?>

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_platform">Plateforme principale</label>
<select name="platform" id="<?= Moncine\View::escape($fieldPrefix) ?>_platform" data-game-platform-select>
    <option value="">— Choisir —</option>
    <?php foreach ($platformChoices as $key => $label): ?>
        <option value="<?= Moncine\View::escape($key) ?>"<?= $selectedPlatform === $key ? ' selected' : '' ?>>
            <?= Moncine\View::escape($label) ?>
        </option>
    <?php endforeach; ?>
</select>

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_annee">Année de sortie</label>
<input type="number" name="annee" id="<?= Moncine\View::escape($fieldPrefix) ?>_annee" min="1970" max="2100" step="1"
       value="<?= (int) ($gameRow['annee'] ?? 0) > 0 ? (int) $gameRow['annee'] : '' ?>"
       placeholder="Ex. 2022">

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_studio">Studio / développeur</label>
<input type="text" name="studio" id="<?= Moncine\View::escape($fieldPrefix) ?>_studio" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['studio'] ?? '')) ?>"
       placeholder="Ex. FromSoftware">

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_editeur">Éditeur</label>
<input type="text" name="editeur" id="<?= Moncine\View::escape($fieldPrefix) ?>_editeur" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['editeur'] ?? '')) ?>"
       placeholder="Ex. Bandai Namco">

<?php
$genreTagsList = $gameRow['genre_list'] ?? Moncine\GameGenre::parseList((string) ($gameRow['genre'] ?? ''));
require MONCINE_ROOT . '/templates/_game_genre_tags_field.php';
?>

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url">Jaquette (URL HTTPS)</label>
<input type="url" name="poster_url" id="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url" maxlength="500"
       placeholder="https://…"
       value="<?= Moncine\View::escape((string) ($gameRow['poster_url'] ?? '')) ?>">

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis">Description (facultatif)</label>
<textarea name="synopsis" id="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis" rows="3"><?= Moncine\View::escape((string) ($gameRow['synopsis'] ?? '')) ?></textarea>
