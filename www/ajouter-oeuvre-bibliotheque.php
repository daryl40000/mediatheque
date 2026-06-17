<?php
/**
 * Ajoute une œuvre du catalogue à la bibliothèque (films, jeux ou magazines).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\CatalogListContext;
use Moncine\OeuvreRepository;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

$oeuvreId = max(0, (int) ($_POST['oeuvre_id'] ?? 0));
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$catalogListContext = CatalogListContext::fromQuery($_POST);

$oeuvre = $oeuvreId > 0 ? (new OeuvreRepository())->findById($oeuvreId) : null;
$domain = $oeuvre !== null
    ? MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM))
    : MediaDomain::FILM;

$backUrl = match ($domain) {
    MediaDomain::JEU => View::oeuvreJeuUrl(
        $oeuvreId,
        $catalogListContext->search(),
        $catalogListContext->sortBy(),
        $catalogListContext->sortDir(),
        $catalogListContext->page()
    ),
    MediaDomain::MAGAZINE => View::oeuvreMagazineUrl(
        $oeuvreId,
        $catalogListContext->search(),
        $catalogListContext->sortBy(),
        $catalogListContext->sortDir(),
        $catalogListContext->page()
    ),
    default => View::addFilmChoiceUrl($oeuvreId),
};

Csrf::rejectUnlessValid($_POST, $backUrl);

if ($oeuvreId <= 0 || $oeuvre === null) {
    header('Location: ' . $backUrl . (str_contains($backUrl, '?') ? '&' : '?') . 'save_error=' . rawurlencode('Œuvre invalide.'));
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$bibId = match ($domain) {
    MediaDomain::JEU => (new GameRepository())->addFromCatalogOeuvre($oeuvreId, $statut, $userId, $foyerId),
    MediaDomain::MAGAZINE => (new MagazineRepository())->addFromCatalogOeuvre($oeuvreId, $statut, $userId, $foyerId),
    default => (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, $statut),
};

if (!is_int($bibId)) {
    $sep = str_contains($backUrl, '?') ? '&' : '?';
    header('Location: ' . $backUrl . $sep . 'save_error=' . rawurlencode((string) $bibId));
    exit;
}

$redirectUrl = match ($domain) {
    MediaDomain::JEU => View::gameUrl($bibId) . '&promoted=1&from_statut=' . rawurlencode($statut),
    MediaDomain::MAGAZINE => View::magazineIssueUrl($bibId) . '&added=1&from_statut=' . rawurlencode($statut),
    default => '/film.php?id=' . $bibId . '&added=1&from_statut=' . rawurlencode($statut),
};

header('Location: ' . $redirectUrl);
exit;
