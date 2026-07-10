<?php
/**
 * Page de résultats — recherche globale bibliothèque + catalogue.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\GlobalSearch;
use Moncine\UserContext;
use Moncine\View;

if (Auth::currentUserId() <= 0) {
    header('Location: /connexion.php');
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$results = ['library' => [], 'catalog' => []];
if (mb_strlen($query) >= 2) {
    $results = (new GlobalSearch())->search($query, $userId, $foyerId, 25);
}

View::render('recherche', [
    'pageTitle' => 'Recherche',
    'query' => $query,
    'libraryResults' => $results['library'],
    'catalogResults' => $results['catalog'],
]);
