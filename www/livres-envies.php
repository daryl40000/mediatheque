<?php
/**
 * Mes envies livres.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\LivreRepository;
use Moncine\MediaContext;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext('/livres-envies.php');

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$books = LivreRepository::isAvailable()
    ? (new LivreRepository())->listInLibrary(
        UserContext::currentUserId(),
        UserContext::currentFoyerId(),
        LibraryStatut::WISHLIST,
        $sortBy,
        $sortDir,
        $query
    )
    : [];

View::render('livres-envies', [
    'pageTitle' => MediaContext::navLabels()['wishlist'],
    'books' => $books,
    'totalCount' => count($books),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => LivreRepository::isAvailable() ? '' : 'Le module livres n’est pas encore disponible.',
]);
