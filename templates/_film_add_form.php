<?php
/**
 * Formulaire d’ajout d’un film.
 *
 * @var array<string, mixed> $film Valeurs par défaut (souvent vides)
 * @var string $formStatut collection|wishlist
 * @var string $cancelUrl
 * @var list<string> $sagaSuggestions
 * @var bool $hasTmdbKey
 */

$formStatut = $formStatut ?? Moncine\LibraryStatut::COLLECTION;
$cancelUrl = $cancelUrl ?? '/films.php';
$sagaSuggestions = $sagaSuggestions ?? [];
$usesCatalog = $usesCatalog ?? false;
$hasTmdbKey = $hasTmdbKey ?? false;
$canManageCatalog = $canManageCatalog ?? Moncine\UserContext::canManageCatalog();
$prefillOeuvreId = (int) ($prefillOeuvreId ?? 0);
$catalogLinked = $usesCatalog && $prefillOeuvreId > 0;
$showCatalogEditFields = $canManageCatalog && !$catalogLinked;
$showLibraryFields = $canManageCatalog || $catalogLinked;
?>
<form method="post" action="/enregistrer-film.php" class="film-edit-form import-form"
      data-can-manage-catalog="<?= $canManageCatalog ? '1' : '0' ?>">
    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
    <input type="hidden" name="statut" value="<?= Moncine\View::escape($formStatut) ?>">

    <fieldset>
        <legend>Informations principales</legend>

        <?php
        $fieldPrefix = 'add';
        require MONCINE_ROOT . '/templates/_film_content_kind_fields.php';
        ?>

        <label for="add_titre">Titre <span class="required">*</span></label>
        <?php if ($usesCatalog): ?>
            <input type="hidden" name="oeuvre_id" id="add_oeuvre_id"
                   value="<?= $prefillOeuvreId > 0 ? $prefillOeuvreId : '' ?>">
            <div class="catalog-title-autocomplete" id="catalog-title-autocomplete"
                 data-search-url="/rechercher-oeuvres.php">
                <input type="text" name="titre" id="add_titre" required autofocus
                       class="catalog-title-autocomplete__input"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       placeholder="Tapez le titre — choisissez dans le catalogue si proposé"
                       value="<?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>"
                       aria-autocomplete="list" aria-controls="catalog-title-suggestions"
                       aria-expanded="false">
                <ul class="catalog-title-autocomplete__list" id="catalog-title-suggestions"
                    role="listbox" hidden></ul>
            </div>
            <p class="hint catalog-title-autocomplete__hint">
                Les suggestions affichent <strong>titre — réalisateur (année)</strong>
                pour distinguer les homonymes. Choisir une ligne réutilise la fiche catalogue.
            </p>
        <?php else: ?>
            <input type="text" name="titre" id="add_titre" required autofocus
                   placeholder="ex. Le Parrain"
                   value="<?= Moncine\View::escape((string) ($film['titre'] ?? '')) ?>">
        <?php endif; ?>

        <?php if ($usesCatalog && !$canManageCatalog && !$catalogLinked): ?>
            <p class="alert alert-info" data-film-pick-catalog-hint>
                Tapez le titre puis <strong>cliquez sur une suggestion du catalogue</strong> pour afficher les champs de votre exemplaire.
            </p>
        <?php endif; ?>

        <?php if ($catalogLinked && !$canManageCatalog): ?>
            <p class="alert alert-info">
                Ce film est déjà au catalogue. Indiquez seulement les détails de <strong>votre exemplaire</strong> (support, saga…).
            </p>
        <?php endif; ?>
    </fieldset>

    <div data-film-catalog-edit-fields<?= $showCatalogEditFields ? '' : ' hidden' ?>>
        <fieldset>
            <legend>Informations catalogue</legend>

            <label for="add_realisateur">Réalisateur</label>
            <input type="text" name="realisateur" id="add_realisateur"
                   placeholder="ex. Francis Ford Coppola"
                   value="<?= Moncine\View::escape((string) ($film['realisateur'] ?? '')) ?>">

            <label for="add_annee">Année</label>
            <input type="text" name="annee" id="add_annee" inputmode="numeric" pattern="[0-9]{4}"
                   placeholder="1972"
                   value="<?= (int) ($film['annee'] ?? 0) > 0 ? (int) $film['annee'] : '' ?>">

            <label for="add_styles">Style(s)</label>
            <input type="text" name="styles" id="add_styles"
                   placeholder="Drame, Policier"
                   value="<?= Moncine\View::escape((string) ($film['styles'] ?? '')) ?>">
        </fieldset>

        <fieldset>
            <legend>Compléments catalogue (optionnel)</legend>

            <label for="add_titre_original">Titre original</label>
            <input type="text" name="titre_original" id="add_titre_original"
                   value="<?= Moncine\View::escape((string) ($film['titre_original'] ?? '')) ?>">

            <label for="add_acteur_1">Acteur principal</label>
            <input type="text" name="acteur_1" id="add_acteur_1"
                   value="<?= Moncine\View::escape((string) ($film['acteur_1'] ?? '')) ?>">

            <label for="add_duree">Durée</label>
            <input type="text" name="duree" id="add_duree" placeholder="1h56 ou 116"
                   value="<?= Moncine\View::escape(Moncine\FilmManualEdit::dureeForInput((int) ($film['duree_min'] ?? 0))) ?>">

            <label for="add_poster_url">Affiche (URL HTTPS)</label>
            <input type="text" name="poster_url" id="add_poster_url"
                   placeholder="https://… (copiée dans /posters/ à l’enregistrement)"
                   value="<?= Moncine\View::escape((string) ($film['poster_url'] ?? '')) ?>">

            <label for="add_synopsis">Synopsis</label>
            <textarea name="synopsis" id="add_synopsis" rows="4"
                      placeholder="Résumé du film…"><?= Moncine\View::escape((string) ($film['synopsis'] ?? '')) ?></textarea>

            <label for="add_tmdb">Identifiant TMDB</label>
            <input type="text" name="tmdb_id" id="add_tmdb"
                   placeholder="78 ou /movie/78"
                   value="<?= (int) ($film['tmdb_id'] ?? 0) > 0 ? (int) $film['tmdb_id'] : '' ?>">
        </fieldset>
    </div>

    <div data-film-library-fields<?= $showLibraryFields ? '' : ' hidden' ?>>

        <?php if ($formStatut === Moncine\LibraryStatut::COLLECTION): ?>
            <fieldset>
                <legend>Mon exemplaire</legend>

                <label for="add_support_physique">Support physique</label>
                <select name="support_physique" id="add_support_physique">
                    <option value="">— Non renseigné —</option>
                    <?php
                    $currentSupport = (string) ($film['support_physique'] ?? '');
                    foreach (Moncine\SupportPhysique::choices() as $key => $label):
                        $sel = $currentSupport === $key ? ' selected' : '';
                        ?>
                        <option value="<?= Moncine\View::escape($key) ?>"<?= $sel ?>><?= Moncine\View::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="add_format_image">Format image</label>
                <input type="text" name="format_image" id="add_format_image"
                       placeholder="Blu-ray, DVD…"
                       value="<?= Moncine\View::escape((string) ($film['format_image'] ?? '')) ?>">

                <label for="add_format_son">Bande sonore</label>
                <input type="text" name="format_son" id="add_format_son"
                       placeholder="VF, VOST…"
                       value="<?= Moncine\View::escape((string) ($film['format_son'] ?? '')) ?>">
            </fieldset>
        <?php endif; ?>

        <fieldset>
            <legend>Organisation personnelle (optionnel)</legend>

            <label for="add_saga">Saga</label>
            <input type="text" name="saga" id="add_saga" list="add_saga_list"
                   value="<?= Moncine\View::escape((string) ($film['saga'] ?? '')) ?>">
            <?php if ($sagaSuggestions !== []): ?>
                <datalist id="add_saga_list">
                    <?php foreach ($sagaSuggestions as $sagaHint): ?>
                        <option value="<?= Moncine\View::escape($sagaHint) ?>">
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        </fieldset>

    </div>

    <?php require MONCINE_ROOT . '/templates/_film_save_actions.php'; ?>
</form>
