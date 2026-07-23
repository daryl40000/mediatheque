<?php
/**
 * Configuration Médiathèque (fork Monciné).
 *
 * Nom affiché : Médiathèque (MONCINE_APP_NAME).
 * Identifiants techniques MONCINE_* / moncine.db / namespace Moncine\ :
 * conservés volontairement — doc/conventions-techniques.md
 *
 * Structure :
 *   www/     racine web
 *   lib/     code PHP
 *   data/    SQLite, clés API, affiches (hors git)
 */

declare(strict_types=1);

// Racine du projet = parent de lib/
define('MONCINE_ROOT', dirname(__DIR__));

// Dossier web (www) — utile pour les liens et includes de templates
define('MONCINE_WWW', MONCINE_ROOT . '/www');

// Données : MONCINE_DATA_PATH peut pointer vers un dossier hors dépôt (serveur, conteneur…).
$dataPath = getenv('MONCINE_DATA_PATH');
if ($dataPath === false || $dataPath === '') {
    $dataPath = MONCINE_ROOT . '/data';
}
define('MONCINE_DATA', $dataPath);

// Sessions PHP : toujours sous data/sessions (évite /var/lib/php/sessions en dev local).
$moncineSessionDir = getenv('MONCINE_SESSION_PATH');
if (!is_string($moncineSessionDir) || trim($moncineSessionDir) === '') {
    $moncineSessionDir = MONCINE_DATA . '/sessions';
} else {
    $moncineSessionDir = rtrim(trim($moncineSessionDir), '/\\');
}
if (!is_dir($moncineSessionDir)) {
    @mkdir($moncineSessionDir, 0775, true);
}
if (is_dir($moncineSessionDir) && is_writable($moncineSessionDir)) {
    session_save_path($moncineSessionDir);
}
define('MONCINE_SESSION_DIR', $moncineSessionDir);

define('MONCINE_DB_FILE', MONCINE_DATA . '/moncine.db');

// Affiches catalogue (même dossier de données que la base SQLite)
define('MONCINE_POSTERS_DIR', MONCINE_DATA . '/posters');

// Racine des médias volumineux (PDF magazines, livres, exports…) — hors www/.
// Sous-dossiers créés par Moncine : objects/, magazines/, books/, exports/, tmp/, …
$mediaPath = getenv('MONCINE_MEDIA_PATH');
if ($mediaPath === false || $mediaPath === '') {
    $mediaPath = MONCINE_DATA . '/media';
}
define('MONCINE_MEDIA_PATH', rtrim($mediaPath, '/\\'));

// Graine d’installation (CSV catalogue + ZIP affiches) — voir install_seed/README.md
define('MONCINE_INSTALL_SEED_PACKAGE_DIR', MONCINE_ROOT . '/install_seed');
define('MONCINE_INSTALL_SEED_DATA_DIR', MONCINE_DATA . '/install_seed');

// Nom de l'application (affiché dans les pages — produit « Médiathèque »)
define('MONCINE_APP_NAME', 'Médiathèque');

// Version applicative (semver du fork Médiathèque)
// Identifiants techniques MONCINE_* / namespace Moncine\ : voir doc/conventions-techniques.md
define('MONCINE_PACKAGE_VERSION', '0.7.38');

// Derrière un reverse proxy de confiance (YunoHost / Nginx) : 1 pour utiliser X-Real-IP / X-Forwarded-For.
$trustProxy = getenv('MONCINE_TRUST_PROXY');
define(
    'MONCINE_TRUST_PROXY',
    $trustProxy !== false && in_array(strtolower(trim($trustProxy)), ['1', 'true', 'yes'], true)
);

// Repère affiché sur la page import (vérifier que le serveur a bien le dernier code).
define('MONCINE_IMPORT_ENGINE_BUILD', '2026-05-18-library-oeuvre-first');
define('MONCINE_PACKAGE_EDITION', 'app');

// Encodage CSV attendu à l'import (UTF-8 recommandé)
define('MONCINE_CSV_DELIMITER', ';');

// Clé API TMDB (synopsis en français) : variable d'environnement ou fichier data/tmdb_api_key.txt
define('MONCINE_TMDB_KEY_FILE', MONCINE_DATA . '/tmdb_api_key.txt');

// Identifiants IGDB (Twitch Developer) : MONCINE_IGDB_CLIENT_ID / MONCINE_IGDB_CLIENT_SECRET ou data/igdb_credentials.json
define('MONCINE_IGDB_CREDENTIALS_FILE', MONCINE_DATA . '/igdb_credentials.json');

// Clé API Steam (GetOwnedGames) : MONCINE_STEAM_API_KEY ou data/steam_api_key.txt
define('MONCINE_STEAM_API_KEY_FILE', MONCINE_DATA . '/steam_api_key.txt');

// Films traités par clic sur « Enrichir » (évite les timeouts PHP)
define('MONCINE_ENRICH_BATCH_SIZE', 8);

// Films déjà vus : proposables à nouveau après cette durée (24 mois ≈ 2 ans)
define('MONCINE_MIN_DAYS_SINCE_REVIEW_OK', 730);

// Taille maximale du fichier CSV à l’import (5 Mo)
define('MONCINE_CSV_MAX_BYTES', 5 * 1024 * 1024);

// Affiche / couverture (fichier image unique : films, magazines, séries)
define('MONCINE_POSTER_MAX_BYTES', 10 * 1024 * 1024);

// Archive ZIP d’affiches à l’import (admin, import en masse)
define('MONCINE_POSTERS_ZIP_MAX_BYTES', 200 * 1024 * 1024);

// PDF de numéros de magazines (scan complet, fichiers lourds)
define('MONCINE_PDF_MAX_BYTES', 350 * 1024 * 1024);

// Sauvegarde / restauration complète de la base SQLite (admin, maintenance)
define('MONCINE_DB_BACKUP_MAX_BYTES', 128 * 1024 * 1024);
