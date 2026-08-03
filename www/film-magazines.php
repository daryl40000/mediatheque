<?php
/**
 * Magazines qui traitent un film (tests, dossiers, interviews…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Database;
use Moncine\FilmRepository;
use Moncine\MagazineGameLink;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\View;

$oeuvreId = (int) ($_GET['oeuvre_id'] ?? 0);
$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new FilmRepository();

$film = null;
if ($bibId > 0) {
    $film = $repo->findById($bibId);
    if ($film !== null) {
        $oeuvreId = (int) ($film['oeuvre_id'] ?? $oeuvreId);
    }
}

if ($film === null && $oeuvreId > 0) {
    $stmt = Database::getInstance()->prepare(
        'SELECT id AS oeuvre_id, titre, annee, poster_url
         FROM oeuvres
         WHERE id = ? AND media_domain = ?
         LIMIT 1'
    );
    $stmt->execute([$oeuvreId, MediaDomain::FILM]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row !== false) {
        $film = $row;
    }
}

$issues = [];
$offeredIssues = [];
if ($oeuvreId > 0 && MagazineGameLink::isAvailable()) {
    $allIssues = (new MagazineGameLink())->listIssueCoverageForGame($oeuvreId, $userId, $foyerId);
    $partitioned = (new MagazineGameLink())->partitionIssueCoverageByOffer($allIssues);
    $offeredIssues = $partitioned['offered'];
    $issues = $partitioned['coverage'];
}

$filmTitle = (string) ($film['titre'] ?? 'Film');
$backUrl = $bibId > 0
    ? '/film.php?id=' . $bibId
    : ($oeuvreId > 0 ? '/oeuvre-film.php?id=' . $oeuvreId : '/films.php');

if ($film === null) {
    http_response_code(404);
}

// Réutilise le même gabarit que les jeux (liste + notes + moyenne).
View::render('jeu-magazines', [
    'pageTitle' => 'Magazines — ' . $filmTitle,
    'game' => $film,
    'gameTitle' => $filmTitle,
    'issues' => $issues,
    'offeredIssues' => $offeredIssues,
    'backUrl' => $backUrl,
    'oeuvreId' => $oeuvreId,
    'bibId' => $bibId,
    'wideLayout' => true,
]);
