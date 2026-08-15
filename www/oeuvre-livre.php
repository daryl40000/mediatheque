<?php
/**
 * Fiche catalogue d’un livre (lecture).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\LivreEnricher;
use Moncine\LivreGameLink;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureLivreContext('/oeuvre-livre.php');

$oeuvreId = (int) ($_GET['id'] ?? 0);
$repo = new LivreRepository();
$book = $oeuvreId > 0 && LivreRepository::isAvailable()
    ? $repo->findCatalogByOeuvreId($oeuvreId)
    : null;

$linkedGames = [];
if ($book !== null && LivreGameLink::isAvailable()) {
    $linkedGames = (new LivreGameLink())->listGamesForBook($oeuvreId);
}

$libraryBibId = null;
if ($book !== null) {
    $libraryBibId = $repo->findLibraryBibIdForCatalogOeuvre(
        $oeuvreId,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    );
}

if ($book === null) {
    http_response_code(404);
}

$enrichStatus = isset($_GET['enrich']) ? (string) $_GET['enrich'] : null;
$enrichMessage = trim((string) ($_GET['enrich_msg'] ?? ''));

View::render('oeuvre-livre', [
    'pageTitle' => $book !== null ? (string) ($book['titre'] ?? 'Livre') : 'Livre introuvable',
    'book' => $book,
    'oeuvreId' => $oeuvreId,
    'linkedGames' => $linkedGames,
    'libraryBibId' => $libraryBibId,
    'canManageCatalog' => CatalogAdmin::canAccess(),
    'canEnrichOpenLibrary' => LivreEnricher::canEnrich(),
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
]);
