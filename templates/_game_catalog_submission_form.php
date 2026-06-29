<?php
/**
 * Formulaire de proposition / révision d’un jeu au catalogue.
 *
 * @var string $formAction URL POST
 * @var string $fieldPrefix préfixe des id HTML
 * @var array<string, mixed> $game données préremplies
 * @var string $userNote note utilisateur (optionnel)
 * @var bool $showUserNote afficher le champ note
 * @var string $submitLabel libellé du bouton principal
 * @var string $cancelUrl lien annuler
 * @var list<array<string, string>> $hiddenFields champs cachés supplémentaires
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 * @var bool $isReviewMode validation admin (masque le texte utilisateur)
 */
$game = $game ?? [];
$fieldPrefix = $fieldPrefix ?? 'propose_game';
$userNote = $userNote ?? '';
$showUserNote = $showUserNote ?? true;
$isReviewMode = !empty($isReviewMode);
$hiddenFields = $hiddenFields ?? [];
$platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
$knownGenres = $knownGenres ?? [];
$gameRow = is_array($game) ? $game : [];
$catalogPlatformKeys = $gameRow['platform_list'] ?? Moncine\GamePlatformList::catalogKeysFromRow($gameRow);
?>
<?php if (($formAction ?? '') !== ''): ?>
<form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="film-edit-form catalog-submission-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="submission_domain" value="jeu">
    <?php foreach ($hiddenFields as $hidden): ?>
        <input type="hidden" name="<?= Moncine\View::escape((string) ($hidden['name'] ?? '')) ?>"
               value="<?= Moncine\View::escape((string) ($hidden['value'] ?? '')) ?>">
    <?php endforeach; ?>
<?php endif; ?>

    <?php if (!$isReviewMode): ?>
    <p class="hint">
        Renseignez les informations connues. L’administrateur validera avant l’ajout au catalogue partagé.
        Tapez le titre pour vérifier que le jeu n’existe pas déjà — <strong>ne choisissez pas</strong> une suggestion du catalogue.
    </p>
    <?php endif; ?>

    <fieldset>
        <legend>Informations principales</legend>

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_titre">Titre du jeu <span class="required">*</span></label>
        <input type="hidden" name="oeuvre_id" value="0">
        <div class="catalog-title-autocomplete"
             data-game-catalog-autocomplete
             data-search-url="/rechercher-jeux-catalogue.php"
             data-oeuvre-id-input=""
             data-annee-input="<?= Moncine\View::escape($fieldPrefix) ?>_annee"
             data-studio-input="<?= Moncine\View::escape($fieldPrefix) ?>_studio">
            <input type="text" name="titre" id="<?= Moncine\View::escape($fieldPrefix) ?>_titre" required maxlength="200"
                   class="catalog-title-autocomplete__input"
                   autocomplete="off" autocapitalize="off" spellcheck="false"
                   placeholder="Tapez le titre — ne choisissez pas un jeu déjà listé"
                   value="<?= Moncine\View::escape((string) ($gameRow['titre'] ?? '')) ?>"
                   aria-autocomplete="list" aria-expanded="false">
            <ul class="catalog-title-autocomplete__list" role="listbox" hidden></ul>
        </div>

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_platform">Plateformes <span class="required">*</span></label>
        <?php
        $platformFieldName = 'platforms[]';
        $selectedPlatformKeys = $catalogPlatformKeys;
        $legend = 'Plateformes';
        $hint = 'Cochez au moins une plateforme sur laquelle ce titre est disponible.';
        $allowedPlatformKeys = null;
        $hidden = false;
        require MONCINE_ROOT . '/templates/_game_platform_checkboxes.php';
        ?>
        <input type="hidden" name="platform" id="<?= Moncine\View::escape($fieldPrefix) ?>_platform" data-game-platform-legacy
               value="<?= Moncine\View::escape((string) ($gameRow['platform'] ?? Moncine\GamePlatformList::primaryKey($catalogPlatformKeys))) ?>">

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_annee">Année de sortie</label>
        <input type="text" name="annee" id="<?= Moncine\View::escape($fieldPrefix) ?>_annee"
               inputmode="numeric" pattern="[0-9]{4}" placeholder="1995"
               value="<?= (int) ($gameRow['annee'] ?? 0) > 0 ? (int) $gameRow['annee'] : '' ?>">

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_studio">Studio / développeur</label>
        <input type="text" name="studio" id="<?= Moncine\View::escape($fieldPrefix) ?>_studio" maxlength="120"
               placeholder="Ex. Nintendo EAD"
               value="<?= Moncine\View::escape((string) ($gameRow['studio'] ?? '')) ?>">

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_editeur">Éditeur</label>
        <input type="text" name="editeur" id="<?= Moncine\View::escape($fieldPrefix) ?>_editeur" maxlength="120"
               placeholder="Ex. Nintendo"
               value="<?= Moncine\View::escape((string) ($gameRow['editeur'] ?? '')) ?>">
    </fieldset>

    <details class="catalog-admin-form__more">
        <summary>Champs optionnels (genres, jaquette, description…)</summary>
        <fieldset>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_titre_original">Titre anglais (IGDB)</label>
            <input type="text" name="titre_original" id="<?= Moncine\View::escape($fieldPrefix) ?>_titre_original" maxlength="200"
                   value="<?= Moncine\View::escape((string) ($gameRow['titre_original'] ?? '')) ?>">

            <?php
            $genreTagsList = $gameRow['genre_list'] ?? Moncine\GameGenre::parseList((string) ($gameRow['genre'] ?? ''));
            require MONCINE_ROOT . '/templates/_game_genre_tags_field.php';
            ?>

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url">Jaquette (URL HTTPS)</label>
            <input type="text" name="poster_url" id="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url"
                   placeholder="https://…"
                   value="<?= Moncine\View::escape((string) ($gameRow['poster_url'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis">Description</label>
            <textarea name="synopsis" id="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis" rows="4"
                      placeholder="Résumé…"><?= Moncine\View::escape((string) ($gameRow['synopsis'] ?? '')) ?></textarea>
        </fieldset>
    </details>

    <?php if ($showUserNote): ?>
        <fieldset>
            <legend>Message pour l’administrateur</legend>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_user_note">Commentaire (optionnel)</label>
            <textarea name="user_note" id="<?= Moncine\View::escape($fieldPrefix) ?>_user_note" rows="2"
                      maxlength="500"
                      placeholder="Ex. édition physique, version PAL…"><?= Moncine\View::escape($userNote) ?></textarea>
        </fieldset>
    <?php endif; ?>

    <?php if (($submitLabel ?? '') !== ''): ?>
        <p class="form-actions">
            <button type="submit" class="btn btn-primary"><?= Moncine\View::escape($submitLabel) ?></button>
            <a href="<?= Moncine\View::escape($cancelUrl) ?>" class="btn btn-secondary">Annuler</a>
        </p>
    <?php endif; ?>
<?php if (($formAction ?? '') !== ''): ?>
</form>
<?php endif; ?>
