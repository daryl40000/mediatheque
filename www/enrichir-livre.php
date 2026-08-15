<?php
/**
 * Enrichissement Open Library d’un exemplaire livre (met aussi à jour le catalogue).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\LivreEnricher;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres.php');
    exit;
}

MediaDomainGuards::ensureLivreContext('/livre.php');
CatalogAdmin::denyUnlessAccess();

$bibId = (int) ($_POST['book_id'] ?? 0);
$returnUrl = $bibId > 0 ? View::livreUrl($bibId) : '/livres.php';

if ($bibId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

Csrf::rejectUnlessValid($_POST, $returnUrl);

$enricher = new LivreEnricher();
$action = (string) ($_POST['action'] ?? 'enrich');
$keepPoster = isset($_POST['keep_poster']);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

if ($action === 'openlibrary') {
    // Résoudre l’œuvre via enrichOne après avoir trouvé le livre.
    $book = (new LivreRepository())->findByBibId($bibId, $userId, $foyerId);
    $oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
    $result = $oeuvreId > 0
        ? $enricher->correctOeuvreWithOpenLibraryId(
            $oeuvreId,
            (string) ($_POST['openlibrary_id'] ?? ''),
            $keepPoster
        )
        : ['ok' => false, 'not_found' => false, 'message' => 'Livre introuvable.'];
} elseif ($action === 'isbn') {
    $book = (new LivreRepository())->findByBibId($bibId, $userId, $foyerId);
    $oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
    $result = $oeuvreId > 0
        ? $enricher->enrichOeuvreByIsbn($oeuvreId, (string) ($_POST['isbn'] ?? ''), $keepPoster)
        : ['ok' => false, 'not_found' => false, 'message' => 'Livre introuvable.'];
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
