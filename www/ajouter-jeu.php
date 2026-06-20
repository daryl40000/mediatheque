<?php
/**
 * Ajouter un jeu vidéo à la collection ou aux envies.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameFranchiseRepository;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/ajouter-jeu.php');

$statutRaw = trim((string) ($_GET['statut'] ?? ''));
$showChoice = $statutRaw === '';

if (!$showChoice) {
    $statut = LibraryStatut::normalize($statutRaw);
} else {
    $statut = '';
}

$repo = new GameRepository();
$prefillOeuvreId = max(0, (int) ($_GET['oeuvre_id'] ?? 0));
$prefillGame = null;
if ($prefillOeuvreId > 0 && GameRepository::isAvailable()) {
    $prefillGame = $repo->findCatalogByOeuvreId($prefillOeuvreId);
}

View::render('ajouter-jeu', [
    'pageTitle' => $showChoice ? 'Ajouter un jeu' : 'Ajouter — ' . LibraryStatut::label($statut),
    'showChoice' => $showChoice,
    'statut' => $statut,
    'statutLabel' => $showChoice ? '' : LibraryStatut::label($statut),
    'platformChoices' => GamePlatform::choices(),
    'moduleAvailable' => GameRepository::isAvailable(),
    'knownGenres' => GameRepository::isAvailable() ? $repo->listKnownGenres() : [],
    'knownSagas' => GameFranchiseRepository::isAvailable()
        ? (new GameFranchiseRepository())->listKnownSagas()
        : [],
    'saveError' => trim((string) ($_GET['error'] ?? '')),
    'prefillOeuvreId' => $prefillGame !== null ? $prefillOeuvreId : 0,
    'prefillGame' => $prefillGame,
]);
