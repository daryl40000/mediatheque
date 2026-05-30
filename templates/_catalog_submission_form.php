<?php
/**
 * Formulaire de proposition / révision d’une œuvre catalogue.
 *
 * @var string $formAction URL POST
 * @var string $fieldPrefix préfixe des id HTML (ex. propose, review)
 * @var array<string, mixed> $film données préremplies
 * @var string $userNote note utilisateur (optionnel)
 * @var bool $showUserNote afficher le champ note
 * @var string $submitLabel libellé du bouton principal
 * @var string $cancelUrl lien annuler
 * @var list<array<string, string>> $hiddenFields champs cachés supplémentaires
 */
$film = $film ?? [];
$fieldPrefix = $fieldPrefix ?? 'propose';
$userNote = $userNote ?? '';
$showUserNote = $showUserNote ?? true;
$hiddenFields = $hiddenFields ?? [];
?>
<?php if (($formAction ?? '') !== ''): ?>
<form method="post" action="<?= Moncine\View::escape($formAction) ?>" class="film-edit-form catalog-submission-form">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <?php foreach ($hiddenFields as $hidden): ?>
        <input type="hidden" name="<?= Moncine\View::escape((string) ($hidden['name'] ?? '')) ?>"
               value="<?= Moncine\View::escape((string) ($hidden['value'] ?? '')) ?>">
    <?php endforeach; ?>
<?php endif; ?>

    <p class="hint">
        Renseignez les informations connues. L’administrateur validera avant l’ajout au catalogue partagé.
        Utilisez l’autocomplétion pour vérifier que l’œuvre n’existe pas déjà.
    </p>

    <fieldset>
        <legend>Informations principales</legend>

        <?php
        require MONCINE_ROOT . '/templates/_film_content_kind_fields.php';
        ?>

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_titre">Titre <span class="required">*</span></label>
        <input type="hidden" name="oeuvre_id" value="0">
        <div class="catalog-title-autocomplete" data-search-url="/rechercher-oeuvres.php">
            <input type="text" name="titre" id="<?= Moncine\View::escape($fieldPrefix) ?>_titre" required
                   class="catalog-title-autocomplete__input"
                   autocomplete="off" autocapitalize="off" spellcheck="false"
                   placeholder="Tapez le titre — ne choisissez pas une œuvre déjà au catalogue"
                   value="<?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>"
                   aria-autocomplete="list" aria-expanded="false">
            <ul class="catalog-title-autocomplete__list" role="listbox" hidden></ul>
        </div>

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_realisateur">Réalisateur</label>
        <input type="text" name="realisateur" id="<?= Moncine\View::escape($fieldPrefix) ?>_realisateur"
               placeholder="ex. Francis Ford Coppola"
               value="<?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?>">

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_annee">Année</label>
        <input type="text" name="annee" id="<?= Moncine\View::escape($fieldPrefix) ?>_annee"
               inputmode="numeric" pattern="[0-9]{4}" placeholder="1972"
               value="<?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '' ?>">

        <label for="<?= Moncine\View::escape($fieldPrefix) ?>_styles">Style(s)</label>
        <input type="text" name="styles" id="<?= Moncine\View::escape($fieldPrefix) ?>_styles"
               placeholder="Drame, Policier"
               value="<?= Moncine\View::escape((string) ($film['styles'] ?? '')) ?>">
    </fieldset>

    <details class="catalog-admin-form__more">
        <summary>Champs optionnels (synopsis, affiche, TMDB…)</summary>
        <fieldset>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_titre_original">Titre original</label>
            <input type="text" name="titre_original" id="<?= Moncine\View::escape($fieldPrefix) ?>_titre_original"
                   value="<?= Moncine\View::escape((string) ($film['titre_original'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_acteur_1">Acteur principal</label>
            <input type="text" name="acteur_1" id="<?= Moncine\View::escape($fieldPrefix) ?>_acteur_1"
                   value="<?= Moncine\View::escape((string) ($film['acteur_1'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_nationalite">Nationalité / pays</label>
            <input type="text" name="nationalite" id="<?= Moncine\View::escape($fieldPrefix) ?>_nationalite"
                   value="<?= Moncine\View::escape((string) ($film['nationalite'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_duree">Durée</label>
            <input type="text" name="duree" id="<?= Moncine\View::escape($fieldPrefix) ?>_duree"
                   placeholder="1h56 ou 116"
                   value="<?= Moncine\View::escape((string) ($film['duree'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url">Affiche (URL HTTPS)</label>
            <input type="text" name="poster_url" id="<?= Moncine\View::escape($fieldPrefix) ?>_poster_url"
                   placeholder="https://…"
                   value="<?= Moncine\View::escape((string) ($film['poster_url'] ?? '')) ?>">

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis">Synopsis</label>
            <textarea name="synopsis" id="<?= Moncine\View::escape($fieldPrefix) ?>_synopsis" rows="4"
                      placeholder="Résumé…"><?= Moncine\View::escape((string) ($film['synopsis'] ?? '')) ?></textarea>

            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_tmdb">Identifiant TMDB (optionnel)</label>
            <input type="text" name="tmdb_id" id="<?= Moncine\View::escape($fieldPrefix) ?>_tmdb"
                   placeholder="78, /movie/78 ou /tv/1396"
                   value="<?= (int) ($film['tmdb_id'] ?? 0) > 0 ? (int) $film['tmdb_id'] : '' ?>">
            <p class="hint">L’administrateur pourra enrichir la fiche après validation si une clé TMDB est configurée.</p>
        </fieldset>
    </details>

    <?php if ($showUserNote): ?>
        <fieldset>
            <legend>Message pour l’administrateur</legend>
            <label for="<?= Moncine\View::escape($fieldPrefix) ?>_user_note">Commentaire (optionnel)</label>
            <textarea name="user_note" id="<?= Moncine\View::escape($fieldPrefix) ?>_user_note" rows="2"
                      maxlength="500"
                      placeholder="Ex. édition Blu-ray, version longue…"><?= Moncine\View::escape($userNote) ?></textarea>
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
