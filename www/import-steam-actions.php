<?php
/**
 * Import bibliothèque Steam : configuration clé API, préparation et validation.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\SteamConfig;
use Moncine\SteamLibraryImporter;
use Moncine\SteamWebApiClient;
use Moncine\UserContext;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /import.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/import.php');

$action = (string) ($_POST['action'] ?? '');

if ($action === 'save_steam_api_key') {
    if (!CatalogAdmin::canAccess()) {
        header('Location: /import.php');
        exit;
    }
    if (SteamConfig::saveApiKey((string) ($_POST['steam_api_key'] ?? ''))) {
        header('Location: /import.php?steam_key_saved=1');
    } else {
        header('Location: /import.php?steam_key_error=1');
    }
    exit;
}

if ($action === 'clear_steam_api_key') {
    if (!CatalogAdmin::canAccess()) {
        header('Location: /import.php');
        exit;
    }
    if (SteamConfig::clearStoredApiKey()) {
        header('Location: /import.php?steam_key_cleared=1');
    } elseif (SteamConfig::getKeySource() === SteamConfig::SOURCE_ENVIRONMENT) {
        header('Location: /import.php?steam_key_clear_env=1');
    } else {
        header('Location: /import.php?steam_key_clear_error=1');
    }
    exit;
}

if ($action === 'test_steam_api') {
    $userId = UserContext::currentUserId();
    $steamId = (new SteamLibraryImporter())->getUserSteamId($userId);
    if (!SteamConfig::isValidSteamId($steamId)) {
        $params = http_build_query([
            'steam_test' => 'fail',
            'steam_test_msg' => 'SteamID64 manquant ou invalide — renseignez-le dans Paramètres du compte.',
        ]);
        header('Location: /import.php?' . $params);
        exit;
    }

    $games = (new SteamWebApiClient())->getOwnedGames($steamId);
    $ok = $games !== [];
    $message = $ok
        ? 'Connexion OK — ' . count($games) . ' jeu(x) trouvé(s) pour votre compte Steam.'
        : ((new SteamWebApiClient())->getLastError() ?? 'Bibliothèque vide ou inaccessible.');

    $params = http_build_query([
        'steam_test' => $ok ? 'ok' : 'fail',
        'steam_test_msg' => $message,
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

if ($action === 'prepare_steam_import') {
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $importer = new SteamLibraryImporter();
    $result = $importer->buildPreview($userId, $foyerId);

    if (!empty($result['error'])) {
        $params = http_build_query([
            'steam_prepare_error' => 1,
            'steam_prepare_msg' => (string) $result['error'],
        ]);
        header('Location: /import.php?' . $params);
        exit;
    }

    header('Location: /import-steam.php');
    exit;
}

if ($action === 'save_steam_mapping') {
    Csrf::rejectUnlessValid($_POST, '/import-steam.php');

    if (!CatalogAdmin::canAccess()) {
        header('Location: /import-steam.php?steam_map_error=1&steam_map_msg=' . rawurlencode('Action réservée aux administrateurs.'));
        exit;
    }

    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $appid = (int) ($_POST['steam_appid'] ?? 0);
    $oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);

    $result = (new SteamLibraryImporter())->saveManualMapping($userId, $foyerId, $appid, $oeuvreId);
    if ($result === true) {
        header('Location: /import-steam.php?steam_mapped=1');
    } else {
        $params = http_build_query([
            'steam_map_error' => 1,
            'steam_map_msg' => (string) $result,
        ]);
        header('Location: /import-steam.php?' . $params);
    }
    exit;
}

if ($action === 'apply_steam_import') {
    Csrf::rejectUnlessValid($_POST, '/import-steam.php');

    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $importer = new SteamLibraryImporter();

    if (SteamLibraryImporter::canCreateCatalogEntries()) {
        $importAppIds = isset($_POST['import_appids']) && is_array($_POST['import_appids'])
            ? array_map('intval', $_POST['import_appids'])
            : [];
        $proposeAppIds = isset($_POST['propose_appids']) && is_array($_POST['propose_appids'])
            ? array_map('intval', $_POST['propose_appids'])
            : [];
        if ($proposeAppIds !== []) {
            $importAppIds = array_values(array_unique(array_merge($importAppIds, $proposeAppIds)));
            $proposeAppIds = [];
        }
    } else {
        $selectedAppIds = isset($_POST['selected_appids']) && is_array($_POST['selected_appids'])
            ? array_map('intval', $_POST['selected_appids'])
            : [];
        $split = $importer->splitSelectionForUser($userId, $selectedAppIds);
        $importAppIds = $split['import'];
        $proposeAppIds = $split['propose'];
    }

    $result = $importer->applySelected($userId, $foyerId, $importAppIds, $proposeAppIds);
    $_SESSION['steam_import_last_errors'] = $result['errors'];

    $params = http_build_query([
        'steam_import_done' => 1,
        'steam_added' => $result['added'],
        'steam_updated' => $result['updated'],
        'steam_proposed' => $result['proposed'],
        'steam_skipped' => $result['skipped'],
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

header('Location: /import.php');
