<?php
/**
 * Enrichissement TMDB par lots + configuration de la clé API.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmEnricher;
use Moncine\TmdbClient;
use Moncine\TmdbConfig;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /import.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/import.php');

$action = (string) ($_POST['action'] ?? '');

if ($action === 'save_tmdb_key') {
    $key = (string) ($_POST['tmdb_api_key'] ?? '');
    if (TmdbConfig::saveApiKey($key)) {
        header('Location: /import.php?tmdb_key_saved=1');
    } else {
        header('Location: /import.php?tmdb_key_error=1');
    }
    exit;
}

if ($action === 'test_tmdb') {
    $test = (new TmdbClient())->testConnection();
    $params = http_build_query([
        'tmdb_test' => $test['ok'] ? 'ok' : 'fail',
        'tmdb_test_msg' => $test['message'],
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

if ($action === 'enrichir') {
    $force = isset($_POST['force_all']);
    $enricher = new FilmEnricher();
    $result = $enricher->enrichBatch(MONCINE_ENRICH_BATCH_SIZE, $force);
    $remaining = $enricher->countPending();

    $_SESSION['enrich_last_errors'] = $result['errors'];
    $params = http_build_query([
        'enrich_done' => 1,
        'processed' => $result['processed'],
        'enriched' => $result['enriched'],
        'not_found' => $result['not_found'],
        'remaining' => $remaining,
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

header('Location: /import.php');
