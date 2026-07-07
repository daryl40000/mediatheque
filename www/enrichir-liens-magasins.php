<?php
/**
 * Enrichissement des liens magasins GOG / Epic (catalogue).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameDigitalStore;
use Moncine\GogCatalogClient;
use Moncine\EpicCatalogClient;
use Moncine\StoreLinkEnricher;

/**
 * @param list<array{title: string, slug: string}> $results
 * @param callable(array{title: string, slug: string}): string $urlForRow
 */
function storeLinksTestMessage(string $store, array $results, ?string $lastError, callable $urlForRow): string
{
    if ($results === []) {
        return $store . ' : ' . ($lastError ?? 'Aucun résultat.');
    }

    $lines = [$store . ' OK — ' . count($results) . ' résultat(s) :'];
    foreach (array_slice($results, 0, 3) as $row) {
        $title = trim((string) ($row['title'] ?? ''));
        $url = trim($urlForRow($row));
        $lines[] = $title !== '' && $url !== ''
            ? $title . ' → ' . $url
            : $title;
    }

    return implode(' | ', $lines);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /import.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/import.php');

if (!CatalogAdmin::canAccess()) {
    header('Location: /import.php');
    exit;
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'test_gog_catalog') {
    $client = new GogCatalogClient();
    $results = $client->search('Witcher', 3);
    $message = storeLinksTestMessage('GOG', $results, $client->getLastError(), static fn (array $row): string => GogCatalogClient::storeUrl((string) ($row['slug'] ?? '')));
    $params = http_build_query([
        'store_links_test' => $results !== [] ? 'ok' : 'fail',
        'store_links_test_msg' => $message,
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

if ($action === 'test_epic_catalog') {
    $client = new EpicCatalogClient();
    $results = $client->search('Witcher', 3);
    $lastError = $client->getLastError();
    if ($results === [] && Moncine\IgdbStoreLinkResolver::isAvailable()) {
        $resolver = new Moncine\IgdbStoreLinkResolver();
        $igdbLink = $resolver->resolve([
            'titre' => 'The Witcher 3: Wild Hunt',
            'annee' => 2015,
        ], GameDigitalStore::EPIC);
        if ($igdbLink !== null) {
            $results = [[
                'title' => $igdbLink['title'],
                'slug' => $igdbLink['slug'],
                'url' => $igdbLink['url'],
            ]];
            $lastError = $lastError !== null
                ? 'API Epic bloquée — repli IGDB utilisé.'
                : null;
        }
    }
    $message = storeLinksTestMessage('Epic', $results, $lastError, static function (array $row): string {
        $url = trim((string) ($row['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        return EpicCatalogClient::storeUrl((string) ($row['slug'] ?? ''));
    });
    $params = http_build_query([
        'store_links_test' => $results !== [] ? 'ok' : 'fail',
        'store_links_test_msg' => $message,
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

if ($action === 'enrichir_store_links') {
    $stores = [];
    if (!empty($_POST['store_gog'])) {
        $stores[] = GameDigitalStore::GOG;
    }
    if (!empty($_POST['store_epic'])) {
        $stores[] = GameDigitalStore::EPIC;
    }

    $onlyMissing = !isset($_POST['force_all_store_links']);
    $force = !empty($_POST['force_all_store_links']);
    $enricher = new StoreLinkEnricher();
    $result = $enricher->enrichBatch($stores, MONCINE_ENRICH_BATCH_SIZE, $onlyMissing, $force);

    $_SESSION['store_links_enrich_errors'] = $result['errors'];
    $params = http_build_query([
        'store_links_done' => 1,
        'store_links_processed' => $result['processed'],
        'store_links_linked' => $result['linked'],
        'store_links_pending' => $result['pending_review'],
        'store_links_skipped' => $result['skipped'],
    ]);
    header('Location: /import.php?' . $params);
    exit;
}

header('Location: /import.php');
