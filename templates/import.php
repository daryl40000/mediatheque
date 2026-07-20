<?php
$csvMaxMo = (int) (MONCINE_CSV_MAX_BYTES / 1024 / 1024);
?>
<div class="import-page">
<section class="import-page__intro">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Importer / exporter';
    $tag = 'h1';
    $info = 'Deux formats : bibliothèque (tous médias — films, BD, jeux, magazines… : possession, envies, notes) '
        . 'et catalogue partagé (métadonnées, admin). '
        . 'La bibliothèque référence chaque œuvre par son ID catalogue.';
    $infoAria = 'Formats d’import et export';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($info, $infoAria, $tag);
    ?>
    <p class="import-page__meta">
        Moteur serveur : <strong><?= Moncine\View::escape((string) ($importEngineBuild ?? '?')) ?></strong>
    </p>

    <?php require MONCINE_ROOT . '/templates/_upload_limits_warning.php'; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($posterZipMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($posterZipMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($posterRemapMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($posterRemapMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($enrichMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($enrichMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= Moncine\View::escape($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Importer un fichier';
    $info = 'CSV ou ODS. Le type est détecté automatiquement (bibliothèque ou catalogue admin). '
        . 'Taille max. ' . $csvMaxMo . ' Mo.';
    $infoAria = 'Import CSV ou ODS';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($info, $infoAria);
    ?>

    <form method="post" enctype="multipart/form-data" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <?php
        unset($info, $infoHtml, $infoAria);
        $for = 'csv_file';
        $label = 'Fichier CSV ou ODS';
        require MONCINE_ROOT . '/templates/_form_label_info.php';
        ?>
        <input type="file" name="csv_file" id="csv_file" accept=".csv,.ods,text/csv" required>

        <label class="checkbox checkbox-label--with-info">
            <input type="checkbox" name="replace_all" value="1">
            <span class="checkbox-label__text">
                Remplacer toute ma bibliothèque avant import
                <span class="info-tooltip" tabindex="0" aria-label="Remplacement de la bibliothèque">
                    <span class="info-tooltip__icon" aria-hidden="true">i</span>
                    <span class="info-tooltip__popup" role="tooltip">
                        Supprime films, envies et historique de vision avant d’importer le fichier.
                    </span>
                </span>
            </span>
        </label>
        <?php if (!empty($canManageCatalog)): ?>
        <label class="checkbox checkbox-label--with-info">
            <input type="checkbox" name="replace_catalog" value="1">
            <span class="checkbox-label__text">
                <strong>Réinitialiser le catalogue avant import</strong>
                <span class="info-tooltip" tabindex="0" aria-label="Réinitialisation du catalogue">
                    <span class="info-tooltip__icon" aria-hidden="true">i</span>
                    <span class="info-tooltip__popup" role="tooltip">
                        Migration depuis une autre instance : cochez avec un export « CSV catalogue ».
                        Supprime toutes les œuvres et recrée les ID du fichier.
                        Sans cette case, les films existants sont mis à jour sans changer leurs numéros.
                    </span>
                </span>
            </span>
        </label>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Importer</button>
    </form>
</section>

<section class="export-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Exporter ma bibliothèque';
    $info = 'Collection et envies de tous les médias : support, format, saga, domaine, ID catalogue, dernière vision… '
        . 'Sans synopsis ni affiche (déjà dans le catalogue).';
    $infoAria = 'Export bibliothèque';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($info, $infoAria);
    ?>

    <?php if ((int) ($libraryCount ?? 0) === 0): ?>
        <p class="import-page__meta">Aucune entrée en bibliothèque.</p>
    <?php else: ?>
        <p class="import-inline-note"><?= (int) $libraryCount ?> entrée(s) (films + envies).</p>
        <div class="export-actions">
            <form method="post" action="/export.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="scope" value="library">
                <input type="hidden" name="format" value="csv">
                <input type="hidden" name="return" value="/import.php">
                <button type="submit" class="btn btn-secondary">CSV bibliothèque</button>
            </form>
            <form method="post" action="/export.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="scope" value="library">
                <input type="hidden" name="format" value="ods">
                <input type="hidden" name="return" value="/import.php">
                <button type="submit" class="btn btn-secondary">ODS bibliothèque</button>
            </form>
        </div>
        <details class="import-columns-help">
            <summary>Colonnes export bibliothèque</summary>
            <p class="import-columns-help__body"><?= Moncine\View::escape(Moncine\LibraryExportSchema::columnLabelsText()) ?></p>
        </details>
    <?php endif; ?>
</section>

<?php if (!empty($canManageCatalog)): ?>
<section class="export-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Exporter le catalogue (administrateur)';
    $infoHtml = 'Toutes les œuvres partagées (titre, synopsis, TMDB, affiche…). '
        . 'Importez ce fichier sur une autre instance <strong>avant</strong> les exports bibliothèque. '
        . 'Conservez la colonne <strong>ID catalogue</strong> (mêmes numéros que les fichiers '
        . '<code>posters/123.jpg</code>).';
    $infoAria = 'Export catalogue administrateur';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($infoHtml, $infoAria);
    ?>

    <?php if ((int) ($catalogCount ?? 0) === 0): ?>
        <p class="import-page__meta">Catalogue vide.</p>
    <?php else: ?>
        <p class="import-inline-note"><?= (int) $catalogCount ?> œuvre(s) au catalogue.</p>
        <div class="export-actions">
            <form method="post" action="/export.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="scope" value="catalog">
                <input type="hidden" name="format" value="csv">
                <input type="hidden" name="return" value="/import.php">
                <button type="submit" class="btn btn-secondary">CSV catalogue</button>
            </form>
            <form method="post" action="/export.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="scope" value="catalog">
                <input type="hidden" name="format" value="ods">
                <input type="hidden" name="return" value="/import.php">
                <button type="submit" class="btn btn-secondary">ODS catalogue</button>
            </form>
            <form method="post" action="/export.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="scope" value="catalog">
                <input type="hidden" name="format" value="zip">
                <input type="hidden" name="return" value="/import.php">
                <button type="submit" class="btn btn-secondary">ZIP affiches locales</button>
            </form>
        </div>
        <details class="import-columns-help">
            <summary>Colonnes export catalogue</summary>
            <p class="import-columns-help__body"><?= Moncine\View::escape(Moncine\CatalogExportSchema::columnLabelsText()) ?></p>
        </details>
    <?php endif; ?>
</section>

<section class="export-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Import catalogue magazines (JSON)';
    $info = 'Alimente le catalogue partagé (revues et numéros, export ABM ou JSON) sans ajout automatique à votre bibliothèque.';
    $infoAria = 'Import catalogue magazines';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($info, $infoAria);
    ?>
    <p>
        <a href="/import-catalogue-magazines.php" class="btn btn-secondary">Ouvrir l’import magazines JSON</a>
    </p>
</section>
<?php endif; ?>

<section class="enrich-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Enrichir mes films (TMDB)';
    $infoHtml = 'TMDB complète le <strong>catalogue</strong> : synopsis, affiche, acteurs… '
        . 'Clé API sur <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener">themoviedb.org</a>. '
        . 'Environ ' . (int) $enrichBatchSize . ' œuvre(s) par clic'
        . ((int) ($enrichPending ?? 0) > 0 ? ' — ' . (int) $enrichPending . ' restante(s).' : '');
    $infoAria = 'Enrichissement TMDB';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($infoHtml, $infoAria);
    ?>

    <?php if (!empty($tmdbMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($tmdbMessage) ?></div>
    <?php endif; ?>

    <?php if (!$hasTmdbKey): ?>
        <form method="post" action="/enrichir.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_tmdb_key">
            <?php
            unset($info, $infoHtml, $infoAria);
            $for = 'tmdb_api_key';
            $label = 'Clé API TMDB';
            require MONCINE_ROOT . '/templates/_form_label_info.php';
            ?>
            <input type="password" name="tmdb_api_key" id="tmdb_api_key" required autocomplete="off"
                   placeholder="ex. a1b2c3d4e5f6…">
            <button type="submit" class="btn btn-secondary">Enregistrer la clé TMDB</button>
        </form>
    <?php else: ?>
        <p class="import-status import-status--ok">
            <?php if (!empty($tmdbKeyFromEnvironment)): ?>
                Clé active via <code>MONCINE_TMDB_API_KEY</code>
            <?php else: ?>
                Clé TMDB enregistrée
            <?php endif; ?>
        </p>

        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/enrichir.php" class="import-form enrich-actions">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="enrichir">
            <div class="export-actions">
                <button type="submit" class="btn btn-accent">Enrichir le catalogue</button>
                <label class="checkbox checkbox-label--with-info">
                    <input type="checkbox" name="force_all" value="1">
                    <span class="checkbox-label__text">
                        Tout retraiter
                        <span class="info-tooltip" tabindex="0" aria-label="Retraiter tout le catalogue">
                            <span class="info-tooltip__icon" aria-hidden="true">i</span>
                            <span class="info-tooltip__popup" role="tooltip">
                                Force l’enrichissement même pour les fiches déjà complétées.
                            </span>
                        </span>
                    </span>
                </label>
            </div>
        </form>
        <form method="post" action="/enrichir.php" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="test_tmdb">
            <button type="submit" class="btn btn-secondary btn-sm">Tester la connexion TMDB</button>
        </form>

        <details class="import-columns-help tmdb-key-manage">
            <summary>Gérer la clé API TMDB</summary>
            <?php if (!empty($tmdbKeyFromEnvironment)): ?>
                <p class="import-page__meta">
                    Modifiez <code>MONCINE_TMDB_API_KEY</code> dans PHP-FPM, puis rechargez PHP-FPM.
                </p>
            <?php else: ?>
                <form method="post" action="/enrichir.php" class="import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="save_tmdb_key">
                    <label for="tmdb_api_key_replace">Nouvelle clé</label>
                    <input type="password" name="tmdb_api_key" id="tmdb_api_key_replace" required autocomplete="off">
                    <button type="submit" class="btn btn-secondary btn-sm">Remplacer</button>
                </form>
                <form method="post" action="/enrichir.php" class="inline-form tmdb-key-clear-form"
                      onsubmit="return confirm('Supprimer la clé TMDB enregistrée ?');">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="clear_tmdb_key">
                    <button type="submit" class="btn btn-secondary btn-sm">Supprimer la clé</button>
                </form>
            <?php endif; ?>
        </details>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="enrich-panel enrich-panel--games">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Enrichir mes jeux (IGDB)';
    $infoHtml = 'IGDB complète les fiches jeux : jaquette locale, année, studio, éditeur, genres en français. '
        . 'Application <strong>Confidential</strong> sur '
        . '<a href="https://dev.twitch.tv/console/apps" target="_blank" rel="noopener">dev.twitch.tv</a>. '
        . 'Environ ' . (int) $enrichBatchSize . ' jeu(x) par clic'
        . ((int) ($igdbEnrichPending ?? 0) > 0 ? ' — ' . (int) $igdbEnrichPending . ' restant(s).' : '');
    $infoAria = 'Enrichissement IGDB';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($infoHtml, $infoAria);
    ?>

    <?php if (empty($igdbModuleReady)): ?>
        <p class="import-page__meta">Migration IGDB non appliquée — relancez la mise à jour de la base.</p>
    <?php else: ?>

    <?php if (!empty($igdbMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($igdbMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($igdbEnrichMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($igdbEnrichMessage) ?></div>
    <?php endif; ?>

    <?php if (!$hasIgdbCredentials): ?>
        <form method="post" action="/enrichir-jeux.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_igdb_credentials">
            <label for="igdb_client_id">Client ID Twitch / IGDB</label>
            <input type="text" name="igdb_client_id" id="igdb_client_id" required autocomplete="off">
            <label for="igdb_client_secret">Client Secret</label>
            <input type="password" name="igdb_client_secret" id="igdb_client_secret" required autocomplete="off">
            <button type="submit" class="btn btn-secondary">Enregistrer les identifiants IGDB</button>
        </form>
    <?php else: ?>
        <p class="import-status import-status--ok">
            <?php if (!empty($igdbCredentialsFromEnvironment)): ?>
                Identifiants actifs via variables d’environnement IGDB
            <?php else: ?>
                Identifiants IGDB enregistrés
            <?php endif; ?>
        </p>

        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/enrichir-jeux.php" class="import-form enrich-actions">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="enrichir_jeux">
            <div class="export-actions">
                <button type="submit" class="btn btn-accent">Enrichir mes jeux</button>
                <label class="checkbox checkbox-label--with-info">
                    <input type="checkbox" name="force_all_jeux" value="1">
                    <span class="checkbox-label__text">
                        Tout retraiter
                        <span class="info-tooltip" tabindex="0" aria-label="Retraiter tous les jeux">
                            <span class="info-tooltip__icon" aria-hidden="true">i</span>
                            <span class="info-tooltip__popup" role="tooltip">
                                Force l’enrichissement même pour les jeux déjà complétés.
                            </span>
                        </span>
                    </span>
                </label>
                <label class="checkbox checkbox-label--with-info">
                    <input type="checkbox" name="keep_poster" value="1" checked>
                    <span class="checkbox-label__text">
                        Garder les jaquettes existantes
                        <span class="info-tooltip" tabindex="0" aria-label="Conserver les jaquettes">
                            <span class="info-tooltip__icon" aria-hidden="true">i</span>
                            <span class="info-tooltip__popup" role="tooltip">
                                Ne remplace pas une jaquette déjà enregistrée localement.
                            </span>
                        </span>
                    </span>
                </label>
            </div>
        </form>
        <form method="post" action="/enrichir-jeux.php" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="test_igdb">
            <button type="submit" class="btn btn-secondary btn-sm">Tester la connexion IGDB</button>
        </form>

        <details class="import-columns-help tmdb-key-manage">
            <summary>Gérer les identifiants IGDB</summary>
            <?php if (!empty($igdbCredentialsFromEnvironment)): ?>
                <p class="import-page__meta">Modifiez les variables IGDB dans PHP-FPM, puis rechargez PHP-FPM.</p>
            <?php else: ?>
                <form method="post" action="/enrichir-jeux.php" class="import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="save_igdb_credentials">
                    <label for="igdb_client_id_replace">Nouveau Client ID</label>
                    <input type="text" name="igdb_client_id" id="igdb_client_id_replace" required autocomplete="off">
                    <label for="igdb_client_secret_replace">Nouveau Client Secret</label>
                    <input type="password" name="igdb_client_secret" id="igdb_client_secret_replace" required autocomplete="off">
                    <button type="submit" class="btn btn-secondary btn-sm">Remplacer</button>
                </form>
                <form method="post" action="/enrichir-jeux.php" class="inline-form tmdb-key-clear-form"
                      onsubmit="return confirm('Supprimer les identifiants IGDB enregistrés ?');">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="clear_igdb_credentials">
                    <button type="submit" class="btn btn-secondary btn-sm">Supprimer</button>
                </form>
            <?php endif; ?>
        </details>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="export-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Import bibliothèque Steam';
    $infoHtml = '1. Admin : clé API Steam Web ci-dessous. '
        . '2. Utilisateur : SteamID64 dans <a href="/parametres.php">Paramètres du compte</a>. '
        . '3. <strong>Préparer l’import</strong> pour l’aperçu avant validation.';
    $infoAria = 'Import bibliothèque Steam';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($infoHtml, $infoAria);
    ?>

    <?php if (empty($steamModuleReady)): ?>
        <p class="import-page__meta">Migration Steam non appliquée — relancez la mise à jour de la base.</p>
    <?php else: ?>

    <?php if (!empty($steamMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($steamMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($steamImportMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($steamImportMessage) ?></div>
    <?php endif; ?>

    <?php if (!$hasSteamApiKey): ?>
        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/import-steam-actions.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_steam_api_key">
            <?php
            unset($info, $infoHtml, $infoAria);
            $for = 'steam_api_key';
            $label = 'Clé API Steam Web';
            $infoHtml = 'Obtenez une clé sur '
                . '<a href="https://steamcommunity.com/dev/apikey" target="_blank" rel="noopener">steamcommunity.com/dev/apikey</a>.';
            $infoAria = 'Clé API Steam';
            require MONCINE_ROOT . '/templates/_form_label_info.php';
            unset($infoHtml, $infoAria);
            ?>
            <input type="password" name="steam_api_key" id="steam_api_key" required autocomplete="off">
            <button type="submit" class="btn btn-secondary">Enregistrer la clé Steam</button>
        </form>
        <?php else: ?>
        <p class="import-page__meta">Clé API Steam manquante — contactez l’administrateur.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="import-status import-status--ok">
            <?php if (!empty($steamKeyFromEnvironment)): ?>
                Clé active via <code>MONCINE_STEAM_API_KEY</code>
            <?php else: ?>
                Clé API Steam enregistrée
            <?php endif; ?>
        </p>

        <?php if (!empty($hasUserSteamId)): ?>
            <p class="import-status import-status--ok">SteamID64 renseigné (<a href="/parametres.php">Paramètres</a>)</p>
        <?php else: ?>
            <p class="alert alert-warning">
                SteamID64 manquant — <a href="/parametres.php">Paramètres du compte</a>.
            </p>
        <?php endif; ?>

        <div class="export-actions">
            <form method="post" action="/import-steam-actions.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="test_steam_api">
                <button type="submit" class="btn btn-secondary btn-sm"<?= empty($hasUserSteamId) ? ' disabled' : '' ?>>
                    Tester Steam
                </button>
            </form>
            <form method="post" action="/import-steam-actions.php" class="inline-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="action" value="prepare_steam_import">
                <button type="submit" class="btn btn-accent"<?= empty($canPrepareSteamImport) ? ' disabled' : '' ?>>
                    Préparer l’import Steam
                </button>
            </form>
        </div>

        <?php if (!empty($canManageCatalog)): ?>
        <details class="catalog-admin-panel">
            <summary class="catalog-admin-panel__summary">Gérer la clé API Steam</summary>
            <div class="catalog-admin-panel__body">
                <?php if (empty($steamKeyFromEnvironment)): ?>
                <form method="post" action="/import-steam-actions.php" class="import-form">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="save_steam_api_key">
                    <label for="steam_api_key_replace">Nouvelle clé API</label>
                    <input type="password" name="steam_api_key" id="steam_api_key_replace" required autocomplete="off">
                    <button type="submit" class="btn btn-secondary btn-sm">Remplacer la clé</button>
                </form>
                <form method="post" action="/import-steam-actions.php" class="inline-form tmdb-key-clear-form"
                      onsubmit="return confirm('Supprimer la clé Steam enregistrée ?');">
                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                    <input type="hidden" name="action" value="clear_steam_api_key">
                    <button type="submit" class="btn btn-secondary btn-sm">Supprimer</button>
                </form>
                <?php else: ?>
                <p class="import-page__meta">Clé fournie par variable d’environnement — non modifiable ici.</p>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="export-panel">
    <?php
    unset($info, $infoHtml, $infoAria, $class, $tag);
    $title = 'Affiches locales';
    $infoHtml = 'Stockage par <strong>ID catalogue</strong> dans <code>posters/</code> '
        . '(ex. <code>123.jpg</code> = œuvre n°123). Importez le catalogue CSV <strong>avant</strong> le ZIP.';
    $infoAria = 'Affiches locales';
    require MONCINE_ROOT . '/templates/_heading_with_info.php';
    unset($infoHtml, $infoAria);
    ?>
    <p>
        <a href="/ranger-affiches.php" class="btn btn-secondary">Télécharger depuis TMDB (par lots)</a>
    </p>

    <?php if (!empty($canManageCatalog)): ?>
        <?php
        unset($info, $infoHtml, $infoAria, $class, $tag);
        $title = 'Importer une archive ZIP';
        $tag = 'h3';
        $infoHtml = 'Archive « ZIP affiches locales » : dossier <code>posters/</code> ou fichiers '
            . '<code>123.jpg</code> à la racine. Max. archive : '
            . '<strong>' . Moncine\View::escape(Moncine\UploadLimits::maxPostersZipBytesLabel()) . '</strong> ; '
            . 'chaque image : <strong>' . Moncine\View::escape(Moncine\UploadLimits::maxPosterBytesLabel()) . '</strong>. '
            . 'Limites PHP : post <strong>' . Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) . '</strong>, '
            . 'upload <strong>' . Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) . '</strong>. '
            . 'Ou copie SSH du dossier <code>posters/</code> à côté de <code>moncine.db</code>.';
        $infoAria = 'Import ZIP affiches';
        require MONCINE_ROOT . '/templates/_heading_with_info.php';
        unset($infoHtml, $infoAria, $tag);
        ?>
        <form method="post" enctype="multipart/form-data" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="import_posters_zip">
            <label for="posters_zip">Archive ZIP des affiches</label>
            <input type="file" name="posters_zip" id="posters_zip" accept=".zip,application/zip" required>
            <button type="submit" class="btn btn-primary">Importer le ZIP</button>
        </form>

        <?php
        unset($info, $infoHtml, $infoAria, $class, $tag);
        $title = 'Recaler les affiches';
        $tag = 'h3';
        $info = 'Si les numéros de fichiers ne correspondent plus au catalogue, envoyez l’export catalogue '
            . 'de l’ancienne instance (avec ID). Moncine associe via titre + réalisateur puis renomme les fichiers.';
        $infoAria = 'Recalage des affiches';
        require MONCINE_ROOT . '/templates/_heading_with_info.php';
        unset($info, $infoAria, $tag);
        ?>
        <form method="post" enctype="multipart/form-data" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="remap_posters">
            <label for="remap_catalog_csv">Export catalogue (ancienne instance, avec ID)</label>
            <input type="file" name="remap_catalog_csv" id="remap_catalog_csv" accept=".csv,text/csv" required>
            <button type="submit" class="btn btn-secondary">Recaler les affiches</button>
        </form>
    <?php else: ?>
        <p class="import-page__meta">L’import ZIP est réservé à l’administrateur.</p>
    <?php endif; ?>
</section>
</div>
