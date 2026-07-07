<?php
/**
 * Validation manuelle des liens magasins proposés.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogGameStoreLinks;
use Moncine\Csrf;
use Moncine\GameDigitalStore;
use Moncine\GameRepository;
use Moncine\GogCatalogClient;
use Moncine\EpicCatalogClient;
use Moncine\OeuvreStoreLinkRepository;
use Moncine\SecureUrl;

$returnUrl = '/maintenance-catalogue.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $returnUrl);
    exit;
}

CatalogAdmin::denyUnlessAccess();
Csrf::rejectUnlessValid($_POST, $returnUrl);

$action = (string) ($_POST['action'] ?? '');
$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$store = GameDigitalStore::normalizeStoreKey((string) ($_POST['store'] ?? ''));
$links = new OeuvreStoreLinkRepository();

if ($oeuvreId <= 0 || $store === '' || !$links::isAvailable()) {
    header('Location: ' . $returnUrl . '?store_link_error=1');
    exit;
}

if ($action === 'verify_store_link') {
    $links->markVerified($oeuvreId, $store);
    header('Location: ' . $returnUrl . '?store_link_verified=1');
    exit;
}

if ($action === 'reject_store_link') {
    $links->delete($oeuvreId, $store);
    header('Location: ' . $returnUrl . '?store_link_rejected=1');
    exit;
}

if ($action === 'manual_store_link') {
    $url = SecureUrl::sanitizePosterUrl(trim((string) ($_POST['store_url'] ?? '')));
    $slug = trim((string) ($_POST['store_slug'] ?? ''));
    if ($url === '' && $slug !== '') {
        $url = match ($store) {
            GameDigitalStore::GOG => GogCatalogClient::storeUrl($slug),
            GameDigitalStore::EPIC => EpicCatalogClient::storeUrl($slug),
            default => '',
        };
    }

    if ($url === '') {
        header('Location: ' . $returnUrl . '?store_link_error=1');
        exit;
    }

    $links->upsert($oeuvreId, $store, [
        'store_slug' => $slug,
        'store_url' => $url,
        'store_title' => trim((string) ($_POST['store_title'] ?? '')),
        'match_confidence' => null,
        'manually_verified' => true,
    ]);
    $games = new GameRepository();
    $game = $games->findCatalogByOeuvreId($oeuvreId);
    if ($game !== null) {
        CatalogGameStoreLinks::stripCatalogUrlFromOwnership($games, $oeuvreId, $game, $store);
    }
    header('Location: ' . $returnUrl . '?store_link_verified=1');
    exit;
}

header('Location: ' . $returnUrl);
