<section>
    <h1>Importer / exporter</h1>
    <p class="lead">
        Deux formats distincts : votre <strong>bibliothèque</strong> (films possédés, envies, support, notes…)
        et le <strong>catalogue partagé</strong> (métadonnées des œuvres, réservé à l’administrateur).
        La bibliothèque référence chaque film par son <strong>ID catalogue</strong>.
    </p>
    <p class="hint">
        Moteur d’import serveur : <strong><?= Moncine\View::escape((string) ($importEngineBuild ?? '?')) ?></strong>
        (si cette date ne change pas après mise à jour du paquet, le code n’est pas déployé).
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

    <form method="post" enctype="multipart/form-data" class="import-form">
        <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
        <label for="csv_file">Fichier CSV ou ODS</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv,.ods,text/csv" required>

        <label class="checkbox">
            <input type="checkbox" name="replace_all" value="1">
            Remplacer toute ma bibliothèque avant import (films + envies + historique)
        </label>
        <?php if (!empty($canManageCatalog)): ?>
        <label class="checkbox">
            <input type="checkbox" name="replace_catalog" value="1">
            <strong>Réinitialiser le catalogue avant import</strong> (migration : conserve les ID du fichier)
        </label>
        <p class="hint">
            À cocher pour une <strong>migration</strong> avec export « CSV catalogue » de l’ancienne instance.
            Supprime toutes les œuvres et les liens bibliothèque, puis recrée les ID indiqués dans le fichier.
            Sans cette case, Moncine met à jour les films existants <em>sans changer leurs numéros</em>.
        </p>
        <?php endif; ?>
        <p class="hint">
            Le type de fichier est détecté automatiquement : export <em>bibliothèque</em> (léger)
            ou export <em>catalogue</em> (admin).
            Taille max. <?= (int) (MONCINE_CSV_MAX_BYTES / 1024 / 1024) ?> Mo.
        </p>

        <button type="submit" class="btn btn-primary">Importer</button>
    </form>
</section>

<section class="export-panel">
    <h2>Exporter ma bibliothèque</h2>
    <p class="lead">
        Collection <strong>et</strong> liste d’envies : support, format, saga, ID catalogue, dernière vision…
        Sans synopsis ni affiche (déjà dans le catalogue partagé).
    </p>

    <?php if ((int) ($libraryCount ?? 0) === 0): ?>
        <p class="hint">Aucune entrée en bibliothèque.</p>
    <?php else: ?>
        <p class="stats"><?= (int) $libraryCount ?> entrée(s) (films + envies).</p>
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
            <p class="hint"><?= Moncine\View::escape(Moncine\LibraryExportSchema::columnLabelsText()) ?></p>
        </details>
    <?php endif; ?>
</section>

<?php if (!empty($canManageCatalog)): ?>
<section class="export-panel">
    <h2>Exporter le catalogue (administrateur)</h2>
    <p class="lead">
        Toutes les œuvres partagées (titre, synopsis, TMDB, affiche…). Importez ce fichier sur une autre instance
        <strong>avant</strong> les exports bibliothèque des utilisateurs.
        La colonne <strong>ID catalogue</strong> doit être conservée (mêmes numéros que les fichiers
        <code>posters/123.jpg</code>).
    </p>

    <?php if ((int) ($catalogCount ?? 0) === 0): ?>
        <p class="hint">Catalogue vide.</p>
    <?php else: ?>
        <p class="stats"><?= (int) $catalogCount ?> œuvre(s) au catalogue.</p>
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
            <p class="hint"><?= Moncine\View::escape(Moncine\CatalogExportSchema::columnLabelsText()) ?></p>
        </details>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($canManageCatalog)): ?>
<section class="export-panel">
    <h2>Import catalogue magazines (JSON)</h2>
    <p class="lead">
        Alimente le catalogue partagé avec des revues et numéros (export ABM ou autre JSON),
        <strong>sans</strong> les ajouter automatiquement à votre bibliothèque.
    </p>
    <p>
        <a href="/import-catalogue-magazines.php" class="btn btn-secondary">Ouvrir l’import magazines JSON</a>
    </p>
</section>
<?php endif; ?>

<section class="enrich-panel">
    <h2>Enrichir mes films (TMDB)</h2>
    <p class="lead">
        <strong>TMDB</strong> complète les fiches du <strong>catalogue</strong> : synopsis, affiche, acteurs…
        (réservé à l’administrateur sur les fiches individuelles).
    </p>

    <?php if (!empty($tmdbMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($tmdbMessage) ?></div>
    <?php endif; ?>

    <p class="hint">
        Clé API sur
        <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener">themoviedb.org</a>.
    </p>

    <?php if (!$hasTmdbKey): ?>
        <form method="post" action="/enrichir.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_tmdb_key">
            <label for="tmdb_api_key">Clé API TMDB</label>
            <input type="password" name="tmdb_api_key" id="tmdb_api_key" required autocomplete="off"
                   placeholder="ex. a1b2c3d4e5f6…">
            <button type="submit" class="btn btn-secondary">Enregistrer la clé TMDB</button>
        </form>
    <?php else: ?>
        <?php if (!empty($tmdbKeyFromEnvironment)): ?>
            <p class="hint">Clé active via <code>MONCINE_TMDB_API_KEY</code> (serveur).</p>
        <?php else: ?>
            <p class="hint">✓ Clé TMDB enregistrée sur le serveur.</p>
        <?php endif; ?>

        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/enrichir.php" class="import-form enrich-actions">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="enrichir">
            <p class="hint">
                Environ <?= (int) $enrichBatchSize ?> œuvre(s) par clic
                <?php if ((int) ($enrichPending ?? 0) > 0): ?>
                    — <?= (int) $enrichPending ?> restante(s).
                <?php endif; ?>
            </p>
            <div class="export-actions">
                <button type="submit" class="btn btn-accent">Enrichir le catalogue</button>
                <label class="checkbox">
                    <input type="checkbox" name="force_all" value="1">
                    Tout retraiter
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
                <p class="hint">
                    Modifiez <code>MONCINE_TMDB_API_KEY</code> dans PHP-FPM (YunoHost), puis rechargez PHP-FPM.
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
    <h2>Enrichir mes jeux (IGDB)</h2>
    <p class="lead">
        <strong>IGDB</strong> complète les fiches jeux : jaquette (stockée localement), année,
        studio, éditeur et genres traduits en français.
    </p>

    <?php if (empty($igdbModuleReady)): ?>
        <p class="hint">Migration IGDB non appliquée — relancez la mise à jour de la base de données.</p>
    <?php else: ?>

    <?php if (!empty($igdbMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($igdbMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($igdbEnrichMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($igdbEnrichMessage) ?></div>
    <?php endif; ?>

    <p class="hint">
        Créez une application <strong>Confidential</strong> sur
        <a href="https://dev.twitch.tv/console/apps" target="_blank" rel="noopener">dev.twitch.tv</a>
        puis copiez le Client ID et le Client Secret.
    </p>

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
        <?php if (!empty($igdbCredentialsFromEnvironment)): ?>
            <p class="hint">Identifiants actifs via <code>MONCINE_IGDB_CLIENT_ID</code> et <code>MONCINE_IGDB_CLIENT_SECRET</code>.</p>
        <?php else: ?>
            <p class="hint">✓ Identifiants IGDB enregistrés sur le serveur.</p>
        <?php endif; ?>

        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/enrichir-jeux.php" class="import-form enrich-actions">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="enrichir_jeux">
            <p class="hint">
                Environ <?= (int) $enrichBatchSize ?> jeu(x) par clic
                <?php if ((int) ($igdbEnrichPending ?? 0) > 0): ?>
                    — <?= (int) $igdbEnrichPending ?> restant(s).
                <?php endif; ?>
            </p>
            <div class="export-actions">
                <button type="submit" class="btn btn-accent">Enrichir mes jeux</button>
                <label class="checkbox">
                    <input type="checkbox" name="force_all_jeux" value="1">
                    Tout retraiter
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="keep_poster" value="1" checked>
                    Garder les jaquettes existantes
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
                <p class="hint">
                    Modifiez les variables d’environnement IGDB dans PHP-FPM, puis rechargez PHP-FPM.
                </p>
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

<?php if (!empty($canManageCatalog)): ?>
<section class="enrich-panel enrich-panel--store-links" id="liens-magasins-catalogue">
    <h2>Liens magasins catalogue (GOG / Epic)</h2>
    <p class="lead">
        Recherche publique par titre pour proposer des liens vers les pages
        <strong>GOG</strong> et <strong>Epic Games Store</strong> sur les fiches catalogue jeux.
        <strong>Epic</strong> bloque souvent les serveurs : le repli passe par <strong>IGDB</strong>
        (identifiants Twitch sur cette page) quand c’est possible.
        Les matchs incertains sont à valider dans
        <a href="/maintenance-catalogue.php">Maintenance catalogue</a>.
    </p>

    <?php if (empty($storeLinksModuleReady)): ?>
        <p class="hint">Migration <code>063_oeuvre_store_links.sql</code> non appliquée.</p>
    <?php else: ?>
        <?php if (!empty($storeLinksMessage)): ?>
            <div class="alert <?= (isset($_GET['store_links_test']) && $_GET['store_links_test'] !== 'ok') ? 'alert-error' : 'alert-success' ?>">
                <?= Moncine\View::escape($storeLinksMessage) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($storeLinksEnrichMessage)): ?>
            <div class="alert alert-success"><?= Moncine\View::escape($storeLinksEnrichMessage) ?></div>
        <?php endif; ?>

        <?php if ((int) ($storeLinksPendingReview ?? 0) > 0): ?>
            <p class="hint">
                <?= (int) $storeLinksPendingReview ?> lien(s) en attente de validation —
                <a href="/maintenance-catalogue.php">ouvrir la file de relecture</a>.
            </p>
        <?php endif; ?>

        <form method="post" action="/enrichir-liens-magasins.php" class="import-form enrich-actions">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="enrichir_store_links">
            <p class="hint">Environ <?= (int) $enrichBatchSize ?> fiche(s) catalogue par clic.</p>
            <div class="export-actions">
                <label class="checkbox">
                    <input type="checkbox" name="store_gog" value="1" checked>
                    GOG
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="store_epic" value="1" checked>
                    Epic Games Store
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="force_all_store_links" value="1">
                    Tout retraiter (y compris liens déjà validés)
                </label>
            </div>
            <button type="submit" class="btn btn-accent">Enrichir les liens magasins</button>
        </form>

        <form method="post" action="/enrichir-liens-magasins.php" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="test_gog_catalog">
            <button type="submit" class="btn btn-secondary btn-sm">Tester GOG</button>
        </form>
        <form method="post" action="/enrichir-liens-magasins.php" class="inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="test_epic_catalog">
            <button type="submit" class="btn btn-secondary btn-sm">Tester Epic</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="export-panel">
    <h2>Import bibliothèque Steam</h2>
    <p class="lead">
        Récupère vos jeux Steam (GetOwnedGames), les associe à IGDB quand c’est possible,
        puis les ajoute à <strong>Mes jeux</strong> avec le temps de jeu Steam.
    </p>

    <?php if (empty($steamModuleReady)): ?>
        <p class="hint">Migration Steam non appliquée — relancez la mise à jour de la base de données.</p>
    <?php else: ?>

    <?php if (!empty($steamMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($steamMessage) ?></div>
    <?php endif; ?>
    <?php if (!empty($steamImportMessage)): ?>
        <div class="alert alert-success"><?= Moncine\View::escape($steamImportMessage) ?></div>
    <?php endif; ?>

    <p class="hint">
        1. L’administrateur enregistre une <strong>clé API Steam Web</strong> ci-dessous.<br>
        2. Chaque utilisateur renseigne son <strong>SteamID64</strong> dans
        <a href="/parametres.php">Paramètres du compte</a>.<br>
        3. Cliquez sur <strong>Préparer l’import</strong> pour voir l’aperçu avant validation.
    </p>

    <?php if (!$hasSteamApiKey): ?>
        <?php if (!empty($canManageCatalog)): ?>
        <form method="post" action="/import-steam-actions.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="save_steam_api_key">
            <label for="steam_api_key">Clé API Steam Web</label>
            <input type="password" name="steam_api_key" id="steam_api_key" required autocomplete="off">
            <p class="hint">
                Obtenez une clé sur
                <a href="https://steamcommunity.com/dev/apikey" target="_blank" rel="noopener">steamcommunity.com/dev/apikey</a>.
            </p>
            <button type="submit" class="btn btn-secondary">Enregistrer la clé Steam</button>
        </form>
        <?php else: ?>
        <p class="hint">Clé API Steam manquante — demandez à l’administrateur de la configurer.</p>
        <?php endif; ?>
    <?php else: ?>
        <?php if (!empty($steamKeyFromEnvironment)): ?>
            <p class="hint">Clé active via <code>MONCINE_STEAM_API_KEY</code>.</p>
        <?php else: ?>
            <p class="hint">✓ Clé API Steam enregistrée sur le serveur.</p>
        <?php endif; ?>

        <?php if (!empty($hasUserSteamId)): ?>
            <p class="hint">✓ SteamID64 renseigné dans <a href="/parametres.php">Paramètres du compte</a>.</p>
        <?php else: ?>
            <p class="alert alert-warning">
                SteamID64 manquant — renseignez-le dans
                <a href="/parametres.php">Paramètres du compte</a> avant de tester ou d’importer.
            </p>
        <?php endif; ?>

        <form method="post" action="/import-steam-actions.php" class="import-form inline-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="test_steam_api">
            <button type="submit" class="btn btn-secondary btn-sm"<?= empty($hasUserSteamId) ? ' disabled' : '' ?>>
                Tester la connexion Steam
            </button>
        </form>

        <form method="post" action="/import-steam-actions.php" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="prepare_steam_import">
            <button type="submit" class="btn btn-accent"<?= empty($canPrepareSteamImport) ? ' disabled' : '' ?>>
                Préparer l’import Steam
            </button>
        </form>

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
                <p class="hint">Clé fournie par la variable d’environnement — non modifiable depuis cette page.</p>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="export-panel">
    <h2>Affiches locales</h2>
    <p class="lead">
        Les affiches sont stockées par <strong>ID catalogue</strong> dans le dossier
        <code>posters/</code> à côté de <code>moncine.db</code>
        (fichiers <code>123.jpg</code> = œuvre n°123). Importez le catalogue CSV <strong>avant</strong> le ZIP.
    </p>
    <p>
        <a href="/ranger-affiches.php" class="btn btn-secondary">Télécharger depuis TMDB (par lots)</a>
    </p>

    <?php if (!empty($canManageCatalog)): ?>
        <h3>Importer une archive ZIP</h3>
        <p class="hint">
            Format attendu : même archive que « ZIP affiches locales » (dossier <code>posters/</code>
            ou fichiers <code>123.jpg</code> à la racine). Taille max. archive :
            <strong><?= Moncine\View::escape(Moncine\UploadLimits::maxPostersZipBytesLabel()) ?></strong>
            ; chaque image dans le ZIP :
            <strong><?= Moncine\View::escape(Moncine\UploadLimits::maxPosterBytesLabel()) ?></strong> max.
            Limites PHP actuelles :
            post_max_size = <strong><?= Moncine\View::escape(Moncine\UploadLimits::postMaxSizeLabel()) ?></strong>,
            upload_max_filesize = <strong><?= Moncine\View::escape(Moncine\UploadLimits::uploadMaxFilesizeLabel()) ?></strong>.
            Vous pouvez aussi copier le dossier <code>posters/</code> en SSH à côté de <code>moncine.db</code>.
        </p>
        <form method="post" enctype="multipart/form-data" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="import_posters_zip">
            <label for="posters_zip">Archive ZIP des affiches</label>
            <input type="file" name="posters_zip" id="posters_zip" accept=".zip,application/zip" required>
            <button type="submit" class="btn btn-primary">Importer le ZIP</button>
        </form>

        <h3>Recaler les affiches (après import)</h3>
        <p class="hint">
            Si les affiches ont été importées avec de <strong>mauvais numéros</strong> (catalogue importé sans
            conserver les ID), envoyez l’<strong>export catalogue de l’ancienne instance</strong> (avec colonne
            ID catalogue). Moncine associe chaque ancien numéro au film actuel via titre + réalisateur, puis
            renomme les fichiers dans <code>posters/</code>.
        </p>
        <form method="post" enctype="multipart/form-data" class="import-form">
            <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
            <input type="hidden" name="action" value="remap_posters">
            <label for="remap_catalog_csv">Export catalogue (ancienne instance, avec ID)</label>
            <input type="file" name="remap_catalog_csv" id="remap_catalog_csv" accept=".csv,text/csv" required>
            <button type="submit" class="btn btn-secondary">Recaler les affiches</button>
        </form>
    <?php else: ?>
        <p class="hint">L’import ZIP est réservé à l’administrateur (après import du catalogue).</p>
    <?php endif; ?>
</section>
