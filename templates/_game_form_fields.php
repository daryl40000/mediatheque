<?php
/**
 * Champs communs formulaire jeu (ajout / modification).
 *
 * @var array<string, mixed>|null $game
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 * @var list<string> $knownSagas
 */
$game = $game ?? null;
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$knownGenres = $knownGenres ?? [];
$knownSagas = $knownSagas ?? [];
$catalogEditOnly = $catalogEditOnly ?? false;
$libraryEditOnly = $libraryEditOnly ?? false;
$useCatalogAutocomplete = $useCatalogAutocomplete ?? false;
$canManageCatalog = $canManageCatalog ?? Moncine\UserContext::canManageCatalog();
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
$isRemake = !empty($gameRow['is_remake']);
$originalGameOeuvreId = (int) ($gameRow['original_game_oeuvre_id'] ?? 0);
$originalGameLabel = trim((string) ($gameRow['original_game_label'] ?? ''));
$titreFieldId = $gameFormFieldPrefix !== '' ? $gameFormFieldPrefix . '_titre' : 'titre';
$oeuvreIdFieldId = $gameFormFieldPrefix !== '' ? $gameFormFieldPrefix . '_oeuvre_id' : 'oeuvre_id';

$catalogPlatformKeys = $gameRow['platform_list'] ?? Moncine\GamePlatformList::catalogKeysFromRow($gameRow);
$ownedPlatformKeys = $gameRow['owned_platform_list'] ?? Moncine\GamePlatformList::ownedKeysFromRow($gameRow);
$catalogLinked = $libraryEditOnly
    || (!$catalogEditOnly && $useCatalogAutocomplete && (int) ($gameRow['oeuvre_id'] ?? 0) > 0);
$showCatalogEditFields = !$libraryEditOnly && ($catalogEditOnly || ($canManageCatalog && !$catalogLinked));
$showLibraryFields = $libraryEditOnly || (!$catalogEditOnly && ($canManageCatalog || $catalogLinked));
?>
<?php if (!$libraryEditOnly): ?>
<label for="<?= Moncine\View::escape($titreFieldId) ?>">Titre du jeu (français) <span class="required">*</span></label>
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
        <?php if ($canManageCatalog): ?>
        Pour créer une <strong>nouvelle</strong> fiche (extension ou remake), ne sélectionnez pas la liste — saisissez le titre puis cochez le type ci-dessous si besoin.
        <?php else: ?>
        <strong>Choisissez obligatoirement une ligne du catalogue</strong> — vous ne pouvez pas créer une nouvelle fiche jeu vous-même.
        <?php endif; ?>
    </p>
<?php else: ?>
<input type="text" name="titre" id="<?= Moncine\View::escape($titreFieldId) ?>" required maxlength="200"
       value="<?= Moncine\View::escape((string) ($gameRow['titre'] ?? '')) ?>"
       placeholder="Ex. Elden Ring, Gran Turismo 7">
<?php endif; ?>

<?php if ($useCatalogAutocomplete && !$canManageCatalog && !$catalogLinked): ?>
    <p class="alert alert-info" data-game-pick-catalog-hint>
        Tapez le titre puis <strong>cliquez sur une suggestion du catalogue</strong> pour afficher les champs de votre exemplaire (plateformes possédées, supports…).
    </p>
<?php endif; ?>

<?php if ($catalogLinked && !$canManageCatalog && !$libraryEditOnly): ?>
    <p class="alert alert-info">
        Ce jeu est déjà au catalogue partagé. Indiquez seulement <strong>vos plateformes</strong> et les détails de <strong>votre exemplaire</strong>.
    </p>
<?php endif; ?>

<div class="game-catalog-edit-fields" data-game-catalog-edit-fields<?= $showCatalogEditFields ? '' : ' hidden' ?>>

<?php
$titreOriginalFieldId = $gameFormFieldPrefix !== '' ? $gameFormFieldPrefix . '_titre_original' : 'titre_original';
?>
<label for="<?= Moncine\View::escape($titreOriginalFieldId) ?>">Titre anglais (IGDB)</label>
<input type="text" name="titre_original" id="<?= Moncine\View::escape($titreOriginalFieldId) ?>" maxlength="200"
       value="<?= Moncine\View::escape((string) ($gameRow['titre_original'] ?? '')) ?>"
       placeholder="Ex. The Witcher 3: Wild Hunt">
<p class="hint">Renseigné automatiquement par IGDB. Affiché seulement si le titre français ci-dessus est vide.</p>

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
            <label for="original_game_query">Jeu d'origine (catalogue) <span class="required">*</span></label>
            <div class="catalog-title-autocomplete">
                <input type="search" id="original_game_query" name="original_game_query" maxlength="200"
                       value="<?= Moncine\View::escape($originalGameLabel) ?>"
                       placeholder="Tapez le titre du jeu original…"
                       autocomplete="off"
                       class="catalog-title-autocomplete__input"
                       data-game-remake-search>
                <div class="catalog-title-autocomplete__list" role="listbox" hidden data-game-remake-list></div>
            </div>
            <input type="hidden" name="original_game_oeuvre_id" id="original_game_oeuvre_id"
                   value="<?= $originalGameOeuvreId > 0 ? $originalGameOeuvreId : '' ?>"
                   data-game-remake-oeuvre-id>

            <p class="hint" id="original_game_hint"<?= $originalGameLabel !== '' ? '' : ' hidden' ?>>
                Jeu sélectionné : <strong id="original_game_hint_label"><?= Moncine\View::escape($originalGameLabel) ?></strong>
                <button type="button" class="btn btn-sm btn-secondary" data-game-remake-clear>Effacer</button>
            </p>
            <p class="hint">
                Un remake est une nouvelle version d’un jeu existant : les deux fiches restent liées dans le catalogue.
            </p>
        </div>
        </div>
        <?php endif; ?>
    </fieldset>
<?php endif; ?>

<label for="platform"<?= $catalogLinked ? ' hidden' : '' ?>>Plateformes du jeu (catalogue)</label>
<?php
$platformFieldName = 'platforms[]';
$selectedPlatformKeys = $catalogPlatformKeys;
$legend = 'Plateformes disponibles pour ce jeu';
$hint = 'Cochez toutes les plateformes sur lesquelles ce titre existe (ex. PC et PlayStation 5).';
$hidden = $catalogLinked;
$allowedPlatformKeys = null;
$fieldsetExtraAttrs = 'data-game-catalog-platforms-fieldset';
require MONCINE_ROOT . '/templates/_game_platform_checkboxes.php';
?>

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

<?php if (Moncine\GameRepository::hasIgdbMetadataColumns()): ?>
<label for="franchise">Saga (IGDB)</label>
<input type="text" name="franchise" id="franchise" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['franchise'] ?? '')) ?>"
       placeholder="Ex. The Witcher, Final Fantasy"
       autocomplete="off"
       list="game-saga-suggestions">
<?php require MONCINE_ROOT . '/templates/_game_saga_datalist.php'; ?>
<p class="hint">Les sagas déjà présentes dans le catalogue sont proposées pendant la saisie.</p>

<label for="game_mode">Modes de jeu (IGDB)</label>
<input type="text" name="game_mode" id="game_mode" maxlength="200"
       value="<?= Moncine\View::escape((string) ($gameRow['game_mode'] ?? '')) ?>"
       placeholder="Ex. Solo, Multijoueur, Coopératif">

<label for="theme">Thèmes (IGDB)</label>
<input type="text" name="theme" id="theme" maxlength="200"
       value="<?= Moncine\View::escape((string) ($gameRow['theme'] ?? '')) ?>"
       placeholder="Ex. Fantasy, Monde ouvert, Horreur">

<label for="alternative_names">Acronymes (IGDB)</label>
<input type="text" name="alternative_names" id="alternative_names" maxlength="120"
       value="<?= Moncine\View::escape((string) ($gameRow['alternative_names'] ?? '')) ?>"
       placeholder="Ex. GTA, FF, TLoZ">
<p class="hint">Séparez plusieurs valeurs par des virgules. Rempli automatiquement par l’enrichissement IGDB.</p>
<?php endif; ?>

<label for="cover_file">Jaquette catalogue (JPEG, PNG, WebP)</label>
<input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
<p class="hint">Image affichée dans le catalogue partagé.</p>

<label for="poster_url">Ou URL de la jaquette catalogue (facultatif)</label>
<input type="text" name="poster_url" id="poster_url" maxlength="500"
       placeholder="https://… ou /posters/123.jpg"
       value="<?= Moncine\View::escape((string) ($gameRow['poster_url'] ?? '')) ?>">
<p class="hint">Chemin local <code>/posters/…</code> ou URL <strong>HTTPS</strong> ; l’image distante sera téléchargée sur le serveur.</p>

<label for="synopsis">Description catalogue (facultatif)</label>
<textarea name="synopsis" id="synopsis" rows="4"><?= Moncine\View::escape((string) ($gameRow['synopsis'] ?? '')) ?></textarea>

</div>
<?php endif; ?>

<div class="game-library-fields" data-game-library-fields<?= $showLibraryFields ? '' : ' hidden' ?>>

<?php
$platformFieldName = 'owned_platforms[]';
$selectedPlatformKeys = $ownedPlatformKeys !== [] ? $ownedPlatformKeys : [];
$allowedPlatformKeys = $catalogLinked && $catalogPlatformKeys !== [] ? $catalogPlatformKeys : null;
$legend = $libraryEditOnly
    ? 'Mes plateformes'
    : 'Mes plateformes (mon exemplaire)';
$hint = $catalogLinked
    ? 'Cochez les plateformes sur lesquelles vous possédez ce jeu.'
    : 'Indiquez les plateformes que vous possédez (selon celles cochées ci-dessus pour un nouveau jeu).';
$hidden = false;
$fieldsetExtraAttrs = 'data-game-owned-platforms-fieldset';
require MONCINE_ROOT . '/templates/_game_platform_checkboxes.php';
?>
<input type="hidden" name="platform" id="platform" data-game-platform-legacy
       value="<?= Moncine\View::escape((string) ($gameRow['platform'] ?? Moncine\GamePlatformList::primaryKey($catalogPlatformKeys))) ?>">

<?php
$testedOnLinux = !empty($gameRow['tested_on_linux']);
$linuxNotSupported = !empty($gameRow['linux_not_supported']);
$linuxFieldAvailable = Moncine\GameRepository::hasTestedOnLinuxColumn();
$hasPcOwned = in_array(Moncine\GamePlatform::PC, $ownedPlatformKeys, true)
    || in_array(Moncine\GamePlatform::PC, $catalogPlatformKeys, true);
$showLinuxFieldInitially = $linuxFieldAvailable && $hasPcOwned;
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

<?php if ($showManualPlaytimeFields ?? true): ?>
<?php require MONCINE_ROOT . '/templates/_game_manual_playtime_fields.php'; ?>
<?php endif; ?>

<?php
$loanPrefsRow = array_merge(['media_domain' => Moncine\MediaDomain::JEU], $gameRow);
$showNonPretableField = Moncine\GameRepository::hasNonPretableColumn()
    && Moncine\LoanEligibility::canToggleNonPretable($loanPrefsRow);
?>
<?php if ($showNonPretableField): ?>
    <fieldset class="game-loan-fieldset">
        <legend>Prêt entre amis</legend>
        <label class="checkbox-inline">
            <input type="checkbox" name="non_pretable" value="1"
                <?= !empty($gameRow['non_pretable']) ? ' checked' : '' ?>>
            Ne pas prêter cet exemplaire
        </label>
        <p class="hint">
            Si coché, vos amis ne pourront pas demander un prêt pour ce jeu sur votre profil public.
        </p>
    </fieldset>
<?php endif; ?>

</div>
