<?php
/**
 * Enrichissement IGDB par lots + configuration des identifiants Twitch.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameEnricher;
use Moncine\IgdbClient;
use Moncine\IgdbConfig;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /import.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/import.php');

$action = (string) ($_POST['action'] ?? '');

if ($action === 'save_igdb_credentials') {
    if (!CatalogAdmin::canAccess()) {
        header('Location: /import.php');
        exit;
    }
    if (IgdbConfig::saveCredentials([
        'client_id' => (string) ($_POST['igdb_client_id'] ?? ''),
        'client_secret' => (string) ($_POST['igdb_client_secret'] ?? ''),
    ])) {
        header('Location: /import.php?igdb_saved=1');
    } else {
        header('Location: /import.php?igdb_error=1');
    }
    exit;
}

if ($action === 'clear_igdb_credentials') {
    if (!CatalogAdmin::canAccess()) {
        header('Location: /import.php');
        exit;
    }
    if (IgdbConfig::clearStoredCredentials()) {
        header('Location: /import.php?igdb_cleared=1');
    } elseif (IgdbConfig::getCredentialsSource() === IgdbConfig::SOURCE_ENVIRONMENT) {
        header('Location: /import.php?igdb_clear_env=1');
    } else {
        header('Location: /import.php?igdb_clear_error=1');
    }
    exit;
}

if ($action === 'test_igdb') {
    $test = (new IgdbClient())->testConnection();
    $params = http_build_query([
        'igdb_test' => $test['ok'] ? 'ok' : 'fail',
        'igdb_test_msg' => $test['message'],
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

if ($action === 'enrichir_jeux') {
    $force = isset($_POST['force_all_jeux']);
    $keepPoster = isset($_POST['keep_poster']);
    $enricher = new GameEnricher();
    $result = $enricher->enrichBatch(MONCINE_ENRICH_BATCH_SIZE, $force, $keepPoster);
    $remaining = $enricher->countPending();

    $_SESSION['igdb_enrich_last_errors'] = $result['errors'];
    $params = http_build_query([
        'igdb_enrich_done' => 1,
        'igdb_processed' => $result['processed'],
        'igdb_enriched' => $result['enriched'],
        'igdb_not_found' => $result['not_found'],
        'igdb_remaining' => $remaining,
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

header('Location: /import.php');
