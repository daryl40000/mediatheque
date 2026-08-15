<?php
/**
 * Enrichissement Open Library d’un album BD (met aussi à jour le catalogue).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdEnricher;
use Moncine\BdRepository;
use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext('/album-bd.php');
CatalogAdmin::denyUnlessAccess();

$bibId = (int) ($_POST['album_id'] ?? 0);
$returnUrl = $bibId > 0 ? View::bdUrl($bibId) : '/bd.php';

if ($bibId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

Csrf::rejectUnlessValid($_POST, $returnUrl);

$enricher = new BdEnricher();
$action = (string) ($_POST['action'] ?? 'enrich');
$keepPoster = isset($_POST['keep_poster']);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

if ($action === 'openlibrary') {
    $album = (new BdRepository())->findByBibId($bibId, $userId, $foyerId);
    $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
    $result = $oeuvreId > 0
        ? $enricher->correctOeuvreWithOpenLibraryId(
            $oeuvreId,
            (string) ($_POST['openlibrary_id'] ?? ''),
            $keepPoster
        )
        : ['ok' => false, 'not_found' => false, 'message' => 'Album introuvable.'];
} elseif ($action === 'isbn') {
    $album = (new BdRepository())->findByBibId($bibId, $userId, $foyerId);
    $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
    $result = $oeuvreId > 0
        ? $enricher->enrichOeuvreByIsbn($oeuvreId, (string) ($_POST['isbn'] ?? ''), $keepPoster)
        : ['ok' => false, 'not_found' => false, 'message' => 'Album introuvable.'];
} else {
    $result = $enricher->enrichOne($bibId, $userId, $foyerId, $keepPoster);
}

$status = $result['ok'] ? 'ok' : ($result['not_found'] ? 'not_found' : 'error');
$params = http_build_query([
    'enrich' => $status,
    'enrich_msg' => $result['message'],
]);

$sep = str_contains($returnUrl, '?') ? '&' : '?';
header('Location: ' . $returnUrl . $sep . $params);
exit;
