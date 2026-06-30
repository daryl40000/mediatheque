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
$isRemake = !empty($gameRow['is_remake']);
$originalGameOeuvreId = (int) ($gameRow['original_game_oeuvre_id'] ?? 0);
$originalGameLabel = trim((string) ($gameRow['original_game_label'] ?? ''));
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
           value="<?= Moncine\View::escape((string) ($gameRow['titre'] ?? '')) ?>"
           aria-autocomplete="list" aria-controls="catalog-game-title-suggestions"
           aria-expanded="false">
    <ul class="catalog-title-autocomplete__list" id="catalog-game-title-suggestions"
        role="listbox" hidden></ul>
</div>

<?php if (Moncine\GameRepository::hasExtensionColumns() || Moncine\GameRepository::hasRemakeColumns()): ?>
    <fieldset class="game-extension-fieldset" data-game-type-fieldset>
        <legend>Type de fiche</legend>

        <?php if (Moncine\GameRepository::hasExtensionColumns()): ?>
        <div data-game-extension-root>
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

            <p class="hint" data-game-extension-hint<?= $baseGameLabel !== '' ? '' : ' hidden' ?>>
                Jeu sélectionné : <strong data-game-extension-hint-label><?= Moncine\View::escape($baseGameLabel) ?></strong>
                <button type="button" class="btn btn-sm btn-secondary" data-game-extension-clear>Effacer</button>
            </p>
        </div>
        </div>
        <?php endif; ?>

        <?php if (Moncine\GameRepository::hasRemakeColumns()): ?>
        <div data-game-remake-root>
        <label class="checkbox-inline">
            <input type="checkbox" name="is_remake" value="1" data-game-remake-toggle
                <?= $isRemake ? ' checked' : '' ?>>
            Cette fiche est un remake
        </label>

        <div class="game-extension-panel" data-game-remake-panel<?= $isRemake ? '' : ' hidden' ?>>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_original_game_query">Jeu d'origine (catalogue) <span class="required">*</span></label>
            <div class="catalog-title-autocomplete">
                <input type="search" id="<?= Moncine\View::escape($fieldPrefix) ?>_original_game_query" name="original_game_query" maxlength="200"
                       value="<?= Moncine\View::escape($originalGameLabel) ?>"
                       placeholder="Tapez le titre du jeu original…"
                       autocomplete="off"
                       class="catalog-title-autocomplete__input"
                       data-game-remake-search>
                <div class="catalog-title-autocomplete__list" role="listbox" hidden data-game-remake-list></div>
            </div>
            <input type="hidden" name="original_game_oeuvre_id" id="<?= Moncine\View::escape($fieldPrefix) ?>_original_game_oeuvre_id"
                   value="<?= $originalGameOeuvreId > 0 ? $originalGameOeuvreId : '' ?>"
                   data-game-remake-oeuvre-id>

            <p class="hint" data-game-remake-hint<?= $originalGameLabel !== '' ? '' : ' hidden' ?>>
                Jeu sélectionné : <strong data-game-remake-hint-label><?= Moncine\View::escape($originalGameLabel) ?></strong>
                <button type="button" class="btn btn-sm btn-secondary" data-game-remake-clear>Effacer</button>
            </p>
        </div>
        </div>
        <?php endif; ?>
    </fieldset>
<?php endif; ?>

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_platform">Plateformes du jeu</label>
<?php
$catalogPlatformKeys = $gameRow['platform_list'] ?? Moncine\GamePlatformList::catalogKeysFromRow($gameRow);
$platformFieldName = 'platforms[]';
$selectedPlatformKeys = $catalogPlatformKeys;
$legend = 'Plateformes';
$hint = 'Cochez toutes les plateformes sur lesquelles ce titre est disponible.';
$allowedPlatformKeys = null;
$hidden = false;
require MONCINE_ROOT . '/templates/_game_platform_checkboxes.php';
?>
<input type="hidden" name="platform" id="<?= Moncine\View::escape($fieldPrefix) ?>_platform" data-game-platform-legacy
       value="<?= Moncine\View::escape((string) ($gameRow['platform'] ?? Moncine\GamePlatformList::primaryKey($catalogPlatformKeys))) ?>">

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

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url">Jaquette (URL ou /posters/…)</label>
<input type="text" name="poster_url" id="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url" maxlength="500"
       placeholder="https://… ou /posters/123.jpg"
       value="<?= Moncine\View::escape((string) ($gameRow['poster_url'] ?? '')) ?>">

<label for="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis">Description (facultatif)</label>
<textarea name="synopsis" id="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis" rows="3"><?= Moncine\View::escape((string) ($gameRow['synopsis'] ?? '')) ?></textarea>
