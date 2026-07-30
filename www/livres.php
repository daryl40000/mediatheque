<?php
/**
 * Mes livres — collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\LivreRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext('/livres.php');

if (MediaContext::current() !== MediaDomain::LIVRE) {
    header('Location: ' . MediaDomain::collectionPath(MediaDomain::LIVRE));
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$viewMode = \Moncine\CollectionViewMode::normalize((string) ($_GET['view'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$books = [];
$moduleError = '';
if (!LivreRepository::isAvailable()) {
    $moduleError = 'Le module livres n’est pas encore disponible. Rechargez la page dans quelques secondes.';
} else {
    $books = (new LivreRepository())->listInLibrary(
        $userId,
        $foyerId,
        LibraryStatut::COLLECTION,
        $sortBy,
        $sortDir,
        $query
    );
}

View::render('livres', [
    'pageTitle' => MediaContext::navLabels()['collection'],
    'books' => $books,
    'totalCount' => count($books),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'viewMode' => $viewMode,
    'moduleError' => $moduleError,
]);
