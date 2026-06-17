<?php
/**
 * Champs communs formulaire jeu (ajout / modification).
 *
 * @var array<string, mixed>|null $game
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 */
$game = $game ?? null;
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$knownGenres = $knownGenres ?? [];
$useCatalogAutocomplete = $useCatalogAutocomplete ?? false;
$gameFormFieldPrefix = $gameFormFieldPrefix ?? '';

$gameRow = is_array($game) ? $game : [];
$selectedPlatform = (string) ($gameRow['platform'] ?? '');
$physicalSelected = $gameRow['physical_support_list'] ?? Moncine\GamePhysicalSupport::parseList((string) ($gameRow['physical_supports'] ?? ''));
$hasDigital = !empty($gameRow['has_digital_edition']) || !empty($gameRow['is_digital']);
$digitalStoreList = $gameRow['digital_store_list'] ?? Moncine\GameDigitalStore::parseStoredList((string) ($gameRow['digital_stores'] ?? ''));
$pcStoreUrls = [];
$pcStoresSelected = [];
foreach ($digitalStoreList as $entry) {
    $storeKey = (string) ($entry['store'] ?? '');
    if (isset(Moncine\GameDigitalStore::pcStoreChoices()[$storeKey])) {
        $pcStoresSelected[] = $storeKey;
        $pcStoreUrls[$storeKey] = (string) ($entry['url'] ?? '');
    }
}
$consoleStoreKey = Moncine\GameDigitalStore::consoleStoreForPlatform($selectedPlatform);
$consoleStoreLabel = $consoleStoreKey !== null
    ? Moncine\GameDigitalStore::label($consoleStoreKey)
    : '';

$isExtension = !empty($gameRow['is_extension']);
$baseGameOeuvreId = (int) ($gameRow['base_game_oeuvre_id'] ?? 0);
$baseGameLabel = trim((string) ($gameRow['base_game_label'] ?? ''));
$titreFieldId = $gameFormFieldPrefix !== '' ? $gameFormFieldPrefix . '_titre' : 'titre';
$oeuvreIdFieldId = $gameFormFieldPrefix !== '' ? $gameFormFieldPrefix . '_oeuvre_id' : 'oeuvre_id';
?>
<label for="<?= Moncine\View::escape($titreFieldId) ?>">Titre du jeu <span class="required">*</span></label>
<?php if ($useCatalogAutocomplete): ?>
    <input type="hidden" name="oeuvre_id" id="<?= Moncine\View::escape($oeuvreIdFieldId) ?>"
           value="<?= (int) ($gameRow['oeuvre_id'] ?? 0) > 0 ? (int) $gameRow['oeuvre_id'] : '' ?>">
    <div class="catalog-title-autocomplete"
         id="add-game-title-autocomplete"
         data-game-catalog-autocomplete
         data-search-url="/rechercher-jeux-catalogue.php"
         data-oeuvre-id-input="<?= Moncine\View::escape($oeuvreIdFieldId) ?>"
         data-annee-input="annee"
         data-studio-input="studio"
         data-platform-input="platform">
        <input type="text" name="titre" id="<?= Moncine\View::escape($titreFieldId) ?>" required maxlength="200"
               class="catalog-title-autocomplete__input"
               autocomplete="off" autocapitalize="off" spellcheck="false"
               placeholder="Tapez le titre — choisissez dans le catalogue si proposé"
               value="<?= Moncine\View::escape((string) ($gameRow['titre'] ?? '')) ?>"
               aria-autocomplete="list" aria-controls="add-game-title-suggestions"
               aria-expanded="false">
        <ul class="catalog-title-autocomplete__list" id="add-game-title-suggestions"
            role="listbox" hidden></ul>
    </div>
    <p class="hint catalog-title-autocomplete__hint">
        Les suggestions affichent <strong>titre (plateforme · année)</strong>.
        Choisir une ligne réutilise la fiche catalogue partagée.
    </p>
<?php else: ?>
<input type="text" name="titre" id="<?= Moncine\View::escape($titreFieldId) ?>" required maxlength="200"
       value="<?= Moncine\View::escape((string) ($gameRow['titre'] ?? '')) ?>"
       placeholder="Ex. Elden Ring, Gran Turismo 7">
<?php endif; ?>

<?php if (Moncine\GameRepository::hasExtensionColumns() && !$useCatalogAutocomplete): ?>
    <fieldset class="game-extension-fieldset" data-game-extension-root>
        <legend>Type de fiche</legend>

        <label class="checkbox-inline">
            <input type="checkbox" name="is_extension" value="1" data-game-extension-toggle
                <?= $isExtension ? ' checked' : '' ?>>
            Cette fiche est une extension (DLC / add-on)
        </label>

        <div class="game-extension-panel" data-game-extension-panel<?= $isExtension ? '' : ' hidden' ?>>
            <label for="base_game_query">Jeu de base (catalogue) <span class="required">*</span></label>
            <div class="catalog-title-autocomplete">
                <input type="search" id="base_game_query" name="base_game_query" maxlength="200"
                       value="<?= Moncine\View::escape($baseGameLabel) ?>"
                       placeholder="Tapez le titre du jeu…"
                       autocomplete="off"
                       class="catalog-title-autocomplete__input"
                       data-game-extension-search>
                <div class="catalog-title-autocomplete__list" role="listbox" hidden data-game-extension-list></div>
            </div>
            <input type="hidden" name="base_game_oeuvre_id" id="base_game_oeuvre_id"
                   value="<?= $baseGameOeuvreId > 0 ? $baseGameOeuvreId : '' ?>"
                   data-game-extension-oeuvre-id>

            <p class="hint" id="base_game_hint"<?= $baseGameLabel !== '' ? '' : ' hidden' ?>>
                Jeu sélectionné : <strong id="base_game_hint_label"><?= Moncine\View::escape($baseGameLabel) ?></strong>
                <button type="button" class="btn btn-sm btn-secondary" data-game-extension-clear>Effacer</button>
            </p>
            <p class="hint">
                Une extension est une fiche jeu à part entière, mais elle pointe vers un jeu de base du catalogue.
            </p>
        </div>
    </fieldset>
<?php endif; ?>

<label for="platform">Plateforme principale</label>
<select name="platform" id="platform" data-game-platform-select>
    <option value="">— Choisir —</option>
    <?php foreach ($platformChoices as $key => $label): ?>
        <option value="<?= Moncine\View::escape($key) ?>"<?= $selectedPlatform === $key ? ' selected' : '' ?>>
            <?= Moncine\View::escape($label) ?>
        </option>
    <?php endforeach; ?>
    </select>

<?php
$testedOnLinux = !empty($gameRow['tested_on_linux']);
$linuxNotSupported = !empty($gameRow['linux_not_supported']);
$linuxFieldAvailable = Moncine\GameRepository::hasTestedOnLinuxColumn();
$showLinuxFieldInitially = $linuxFieldAvailable && $selectedPlatform === Moncine\GamePlatform::PC;
?>
<?php if ($linuxFieldAvailable): ?>
    <div class="game-linux-field" data-game-linux-field<?= $showLinuxFieldInitially ? '' : ' hidden' ?>>
        <fieldset class="game-linux-fieldset">
            <legend class="visually-hidden">Compatibilité Linux</legend>
            <label class="checkbox-inline game-linux-form__label">
                <input type="checkbox" name="tested_on_linux" value="1" data-linux-tested
                    <?= $testedOnLinux ? ' checked' : '' ?>>
                Testé sur Linux
            </label>
            <?php if (Moncine\GameRepository::hasLinuxNotSupportedColumn()): ?>
                <label class="checkbox-inline game-linux-form__label">
                    <input type="checkbox" name="linux_not_supported" value="1" data-linux-not-supported
                        <?= $linuxNotSupported ? ' checked' : '' ?>>
                    Linux non supporté
                </label>
            <?php endif; ?>
        </fieldset>
        <p class="hint">
            Jeux PC uniquement — cochez une seule option pour indiquer si le jeu a été testé sous Linux.
            Sans case cochée, le statut Linux reste inconnu.
        </p>
    </div>
<?php endif; ?>

<label for="annee">Année de sortie</label>
<input type="number" name="annee" id="annee" min="1970" max="2100" step="1"
       value="<?= (int) ($gameRow['annee'] ?? 0) > 0 ? (int) $gameRow['annee'] : '' ?>"
       placeholder="Ex. 2022">

<label for="studio">Studio / développeur</label>
<input type="text" name="studio" id="studio" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['studio'] ?? '')) ?>"
       placeholder="Ex. FromSoftware">

<label for="editeur">Éditeur</label>
<input type="text" name="editeur" id="editeur" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['editeur'] ?? '')) ?>"
       placeholder="Ex. Bandai Namco">

<?php
$genreTagsList = $gameRow['genre_list'] ?? Moncine\GameGenre::parseList((string) ($gameRow['genre'] ?? ''));
require MONCINE_ROOT . '/templates/_game_genre_tags_field.php';
?>

<fieldset class="game-editions-fieldset" data-game-editions-root>
    <legend>Exemplaires possédés</legend>

    <p class="hint">Cochez les supports que vous avez dans votre collection (plusieurs choix possibles).</p>

    <div class="game-physical-supports">
        <span class="game-editions-fieldset__label">Support physique</span>
        <?php foreach (Moncine\GamePhysicalSupport::choices() as $key => $label): ?>
            <label class="checkbox-inline">
                <input type="checkbox" name="physical_supports[]" value="<?= Moncine\View::escape($key) ?>"
                    <?= in_array($key, $physicalSelected, true) ? ' checked' : '' ?>>
                <?= Moncine\View::escape($label) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="game-digital-section">
        <label class="checkbox-inline">
            <input type="checkbox" name="is_digital" id="game_is_digital" value="1" data-game-digital-toggle
                <?= $hasDigital ? ' checked' : '' ?>>
            Version dématérialisée
        </label>

        <div class="game-digital-panel game-digital-panel--pc" data-game-digital-pc hidden>
            <p class="hint">Magasins PC — vous pouvez en cocher plusieurs et renseigner le lien vers la page du magasin.</p>
            <?php foreach (Moncine\GameDigitalStore::pcStoreChoices() as $storeKey => $storeLabel): ?>
                <div class="game-digital-store-row">
                    <label class="checkbox-inline">
                        <input type="checkbox" name="digital_pc_stores[]" value="<?= Moncine\View::escape($storeKey) ?>"
                            data-game-pc-store="<?= Moncine\View::escape($storeKey) ?>"
                            <?= in_array($storeKey, $pcStoresSelected, true) ? ' checked' : '' ?>>
                        <?= Moncine\View::escape($storeLabel) ?>
                    </label>
                    <label class="game-digital-store-url">
                        <span class="visually-hidden">Lien <?= Moncine\View::escape($storeLabel) ?></span>
                        <input type="url" name="digital_store_url[<?= Moncine\View::escape($storeKey) ?>]"
                               maxlength="500" placeholder="https://… page du magasin"
                               value="<?= Moncine\View::escape((string) ($pcStoreUrls[$storeKey] ?? '')) ?>"
                               data-game-pc-store-url="<?= Moncine\View::escape($storeKey) ?>">
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="game-digital-panel game-digital-panel--console" data-game-digital-console hidden>
            <p class="hint">
                Store console :
                <strong data-game-console-store-label><?= Moncine\View::escape($consoleStoreLabel !== '' ? $consoleStoreLabel : '—') ?></strong>.
                Pas de lien personnalisé pour les versions console.
            </p>
        </div>
    </div>
</fieldset>

<label for="cover_file">Jaquette (JPEG, PNG, WebP)</label>
<input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
<p class="hint">Image affichée dans la liste et sur la fiche jeu. Laissez vide pour conserver l’actuelle.</p>

<label for="poster_url">Ou URL de la jaquette (facultatif)</label>
<input type="url" name="poster_url" id="poster_url" maxlength="500" placeholder="https://…">
<p class="hint">L’image sera <strong>téléchargée et enregistrée sur le serveur</strong> (comme pour les films). HTTPS uniquement.</p>

<label for="synopsis">Description (facultatif)</label>
<textarea name="synopsis" id="synopsis" rows="4"><?= Moncine\View::escape((string) ($gameRow['synopsis'] ?? '')) ?></textarea>
