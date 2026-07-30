<?php
/**
 * Modifier un livre de la bibliothèque.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LivreCategory;
use Moncine\LivreGameLink;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext();

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();
$book = $bibId > 0 && LivreRepository::isAvailable()
    ? $repo->findByBibId($bibId, $userId, $foyerId)
    : null;

if ($book === null) {
    header('Location: /livres.php');
    exit;
}

$linkedGames = LivreGameLink::isAvailable()
    ? (new LivreGameLink())->listGamesForBook((int) ($book['oeuvre_id'] ?? 0))
    : [];

View::render('modifier-livre', [
    'pageTitle' => 'Modifier — ' . (string) ($book['titre'] ?? ''),
    'book' => $book,
    'bookId' => $bibId,
    'linkedGames' => $linkedGames,
    'knownCategories' => LivreCategory::suggestionLabels(),
    'sagaSuggestions' => (new LivreRepository())->listKnownSagas(),
    'moduleAvailable' => true,
    'saveError' => trim((string) ($_GET['error'] ?? '')),
    'saved' => isset($_GET['saved']),
]);
