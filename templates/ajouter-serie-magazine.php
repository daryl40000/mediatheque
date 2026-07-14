<?php
/** @var array<string, string> $publicationTypes */
/** @var array<string, mixed> $series */
/** @var string $error */
/** @var bool $moduleAvailable */
/** @var bool $canManageCatalog */
/** @var string $catalogAddedMessage */
$moduleAvailable = $moduleAvailable ?? \Moncine\MagazineRepository::isAvailable();
$canManageCatalog = $canManageCatalog ?? \Moncine\CatalogAdmin::canAccess();
$catalogAddedMessage = $catalogAddedMessage ?? '';
?>
<section>
    <h1>Ajouter une série magazine</h1>
    <p class="lead">
        Choisissez une revue déjà présente au <strong>catalogue partagé</strong>,
        ou créez une nouvelle série si elle n’existe pas encore.
    </p>
    <p><a href="/magazines.php" class="btn btn-secondary btn-sm">← <?= Moncine\View::escape(Moncine\MediaContext::navLabels()['collection']) ?></a></p>

    <?php if ($canManageCatalog): ?>
        <p class="hint">
            Administrateur :
            <a href="/import-catalogue-magazines.php">Importer des revues depuis un JSON (ABM)</a>
        </p>
    <?php endif; ?>

    <?php if ($catalogAddedMessage !== ''): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($catalogAddedMessage) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-warning"><?= Moncine\View::escape($error) ?></div>
    <?php endif; ?>

    <?php if ($moduleAvailable): ?>
    <details class="catalog-admin-panel" open>
        <summary class="catalog-admin-panel__summary">Ajouter une revue du catalogue</summary>
        <div class="catalog-admin-panel__body">
            <p class="hint">
                Tapez le nom de la revue (ex. Tilt, Joystick). Si elle a été importée au catalogue,
                elle apparaît ici — vous l’ajoutez à <strong>vos</strong> magazines sans recréer les numéros.
            </p>
            <form method="post" action="/enregistrer-serie-magazine.php" class="import-form" id="magazine-series-catalog-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="from_catalog">
                <input type="hidden" name="catalog_series_id" id="catalog_series_id" value="">

                <label for="catalog_series_search">Rechercher au catalogue</label>
                <div class="catalog-title-autocomplete magazine-series-catalog-autocomplete"
                     id="magazine-series-catalog-autocomplete"
                     data-magazine-series-catalog-autocomplete
                     data-search-url="/rechercher-series-catalogue.php"
                     data-series-id-input="catalog_series_id">
                    <input type="text" id="catalog_series_search"
                           class="catalog-title-autocomplete__input"
                           autocomplete="off" autocapitalize="off" spellcheck="false"
                           placeholder="Ex. Tilt, PC Jeux…"
                           aria-autocomplete="list"
                           aria-controls="magazine-series-catalog-suggestions"
                           aria-expanded="false">
                    <ul class="catalog-title-autocomplete__list" id="magazine-series-catalog-suggestions"
                        role="listbox" hidden></ul>
                </div>
                <p class="hint catalog-title-autocomplete__hint" id="catalog_series_hint" hidden></p>

                <button type="submit" class="btn btn-primary" id="catalog_series_submit" disabled>
                    Ajouter à mes magazines
                </button>
            </form>
        </div>
    </details>
    <?php endif; ?>

    <details class="catalog-admin-panel"<?= $moduleAvailable ? '' : ' open' ?>>
        <summary class="catalog-admin-panel__summary">Créer une nouvelle revue</summary>
        <div class="catalog-admin-panel__body">
            <p class="hint">
                Utilisez ce formulaire seulement si la revue n’existe pas encore au catalogue.
                Elle sera ajoutée à votre bibliothèque et au catalogue partagé.
            </p>

            <form method="post" action="/enregistrer-serie-magazine.php" class="import-form" enctype="multipart/form-data">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="create">

                <label for="titre">Titre de la série <span class="required">*</span></label>
                <input type="text" name="titre" id="titre" required maxlength="200"
                       value="<?= Moncine\View::escape((string) ($series['titre'] ?? '')) ?>"
                       placeholder="Ex. PC Jeux, Joystick, White Dwarf">

                <label for="publication_type">Type de publication</label>
                <select name="publication_type" id="publication_type">
                    <?php foreach ($publicationTypes as $key => $label): ?>
                        <option value="<?= Moncine\View::escape($key) ?>"
                            <?= ($series['publication_type'] ?? '') === $key ? ' selected' : '' ?>>
                            <?= Moncine\View::escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">Sert à afficher la date de parution (semaine, mois, trimestre…).</p>

                <?php
                $seriesTagsList = Moncine\MagazineSeriesTag::parseList((string) ($series['tags'] ?? ''));
                require MONCINE_ROOT . '/templates/_magazine_series_tags_field.php';
                ?>

                <?php
                $seriesCategoriesList = Moncine\MagazineSeriesCategory::parseList((string) ($series['categories'] ?? ''));
                $knownCategories = $knownCategories ?? Moncine\MagazineSeriesCategory::suggestionLabels();
                require MONCINE_ROOT . '/templates/_magazine_series_categories_field.php';
                ?>

                <label for="cover_file">Logo ou couverture type (JPEG, PNG, WebP)</label>
                <input type="file" name="cover_file" id="cover_file" accept="image/jpeg,image/png,image/webp">
                <p class="hint">Image affichée dans « Mes magazines » pour repérer la revue (facultatif).</p>

                <label for="editeur">Éditeur</label>
                <input type="text" name="editeur" id="editeur" maxlength="120"
                       value="<?= Moncine\View::escape((string) ($series['editeur'] ?? '')) ?>">

                <label for="issn">ISSN</label>
                <input type="text" name="issn" id="issn" maxlength="20"
                       value="<?= Moncine\View::escape((string) ($series['issn'] ?? '')) ?>">

                <label for="langue">Langue</label>
                <input type="text" name="langue" id="langue" maxlength="10"
                       value="<?= Moncine\View::escape((string) ($series['langue'] ?? 'fr')) ?>">

                <label for="pays">Pays</label>
                <input type="text" name="pays" id="pays" maxlength="60"
                       value="<?= Moncine\View::escape((string) ($series['pays'] ?? '')) ?>">

                <label for="date_debut">Première parution (optionnel)</label>
                <input type="date" name="date_debut" id="date_debut"
                       value="<?= Moncine\View::escape((string) ($series['date_debut'] ?? '')) ?>">

                <label for="date_fin">Dernière parution (revue arrêtée)</label>
                <input type="date" name="date_fin" id="date_fin"
                       value="<?= Moncine\View::escape((string) ($series['date_fin'] ?? '')) ?>">

                <label for="notes">Notes</label>
                <textarea name="notes" id="notes" rows="3"><?= Moncine\View::escape((string) ($series['notes'] ?? '')) ?></textarea>

                <button type="submit" class="btn btn-secondary">Créer la série</button>
            </form>
        </div>
    </details>
</section>
