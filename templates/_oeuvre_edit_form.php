<?php
/**
 * Formulaire de correction manuelle d’une œuvre catalogue.
 *
 * @var array<string, mixed> $oeuvre
 * @var int $oeuvreId
 * @var bool $editOpen
 * @var string $saveError
 * @var string $catalogueBackUrl
 * @var string $catalogSearch
 * @var string $catalogSort
 * @var string $catalogDir
 * @var int $catalogPage
 */
$editOpen = $editOpen ?? false;
$saveError = $saveError ?? '';
$catalogSearch = $catalogSearch ?? '';
$catalogSort = $catalogSort ?? 'titre';
$catalogDir = $catalogDir ?? 'asc';
$catalogPage = (int) ($catalogPage ?? 1);
?>
<details class="film-edit-panel"<?= $editOpen ? ' open' : '' ?>>
    <summary class="film-edit-panel__summary">Modifier manuellement la fiche catalogue</summary>

    <p class="hint">
        Corrigez le titre, le synopsis, l’affiche, la catégorie, etc. Les champs vides (année, durée)
        peuvent effacer la valeur enregistrée.
    </p>

    <?php if ($saveError !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></div>
    <?php endif; ?>

    <form method="post" action="/modifier-oeuvre.php" class="film-edit-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <input type="hidden" name="oeuvre_id" value="<?= (int) $oeuvreId ?>">
        <input type="hidden" name="catalog_q" value="<?= Moncine\View::escape($catalogSearch) ?>">
        <input type="hidden" name="catalog_sort" value="<?= Moncine\View::escape($catalogSort) ?>">
        <input type="hidden" name="catalog_dir" value="<?= Moncine\View::escape($catalogDir) ?>">
        <input type="hidden" name="catalog_page" value="<?= max(1, $catalogPage) ?>">

        <fieldset>
            <legend>Informations principales</legend>

            <label for="oeuvre_edit_titre">Titre <span class="required">*</span></label>
            <input type="text" name="titre" id="oeuvre_edit_titre" required
                   value="<?= Moncine\View::escape((string) ($oeuvre['titre'] ?? '')) ?>">

            <label for="oeuvre_edit_titre_original">Titre original</label>
            <input type="text" name="titre_original" id="oeuvre_edit_titre_original"
                   placeholder="ex. The Godfather"
                   value="<?= Moncine\View::escape((string) ($oeuvre['titre_original'] ?? '')) ?>">

            <label for="oeuvre_edit_realisateur">Réalisateur</label>
            <input type="text" name="realisateur" id="oeuvre_edit_realisateur"
                   value="<?= Moncine\View::escape((string) ($oeuvre['realisateur'] ?? '')) ?>">

            <label for="oeuvre_edit_acteur_1">Acteur principal 1</label>
            <input type="text" name="acteur_1" id="oeuvre_edit_acteur_1"
                   value="<?= Moncine\View::escape((string) ($oeuvre['acteur_1'] ?? '')) ?>">

            <label for="oeuvre_edit_acteur_2">Acteur principal 2</label>
            <input type="text" name="acteur_2" id="oeuvre_edit_acteur_2"
                   value="<?= Moncine\View::escape((string) ($oeuvre['acteur_2'] ?? '')) ?>">

            <label for="oeuvre_edit_acteur_3">Acteur principal 3</label>
            <input type="text" name="acteur_3" id="oeuvre_edit_acteur_3"
                   value="<?= Moncine\View::escape((string) ($oeuvre['acteur_3'] ?? '')) ?>">

            <label for="oeuvre_edit_annee">Année</label>
            <input type="text" name="annee" id="oeuvre_edit_annee" inputmode="numeric" pattern="[0-9]{4}"
                   placeholder="1982"
                   value="<?= (int) ($oeuvre['annee'] ?? 0) > 0 ? (int) $oeuvre['annee'] : '' ?>">

            <label for="oeuvre_edit_nationalite">Nationalité / pays</label>
            <input type="text" name="nationalite" id="oeuvre_edit_nationalite"
                   placeholder="ex. France, États-Unis"
                   value="<?= Moncine\View::escape((string) ($oeuvre['nationalite'] ?? '')) ?>">

            <label for="oeuvre_edit_duree">Durée</label>
            <input type="text" name="duree" id="oeuvre_edit_duree"
                   placeholder="1h56 ou 116"
                   value="<?= Moncine\View::escape(Moncine\FilmManualEdit::dureeForInput((int) ($oeuvre['duree_min'] ?? 0))) ?>">

            <label for="oeuvre_edit_styles">Style(s)</label>
            <input type="text" name="styles" id="oeuvre_edit_styles"
                   placeholder="Action, Science-fiction"
                   value="<?= Moncine\View::escape((string) ($oeuvre['styles'] ?? '')) ?>">
        </fieldset>

        <fieldset>
            <legend>Catégorie</legend>
            <p class="hint">
                Film, série, documentaire ou spectacle — utilisée pour l’enrichissement TMDB.
            </p>
            <?php
            $film = $oeuvre;
            $fieldPrefix = 'oeuvre_edit';
            require MONCINE_ROOT . '/templates/_film_content_kind_fields.php';
            ?>
        </fieldset>

        <fieldset>
            <legend>Texte &amp; affiche</legend>

            <label for="oeuvre_edit_poster_url">Affiche (URL ou chemin local)</label>
            <input type="text" name="poster_url" id="oeuvre_edit_poster_url"
                   placeholder="https://… ou /posters/123.jpg"
                   value="<?= Moncine\View::escape((string) ($oeuvre['poster_url'] ?? '')) ?>">
            <p class="hint">Une URL HTTPS sera copiée dans <code>/posters/</code> à l’enregistrement.</p>

            <label for="oeuvre_edit_synopsis">Synopsis</label>
            <textarea name="synopsis" id="oeuvre_edit_synopsis" rows="6"
                      placeholder="Résumé…"><?= Moncine\View::escape((string) ($oeuvre['synopsis'] ?? '')) ?></textarea>
        </fieldset>

        <fieldset>
            <legend>TMDB (optionnel)</legend>

            <label for="oeuvre_edit_tmdb">Identifiant TMDB</label>
            <input type="text" name="tmdb_id" id="oeuvre_edit_tmdb"
                   placeholder="78, /movie/78 ou /tv/1396"
                   value="<?= (int) ($oeuvre['tmdb_id'] ?? 0) > 0 ? (int) $oeuvre['tmdb_id'] : '' ?>">
        </fieldset>

        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>
</details>
