<?php
/**
 * Ajouter un jeu vidéo à la collection ou aux envies.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

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

View::render('ajouter-jeu', [
    'pageTitle' => $showChoice ? 'Ajouter un jeu' : 'Ajouter — ' . LibraryStatut::label($statut),
    'showChoice' => $showChoice,
    'statut' => $statut,
    'statutLabel' => $showChoice ? '' : LibraryStatut::label($statut),
    'platformChoices' => GamePlatform::choices(),
    'moduleAvailable' => GameRepository::isAvailable(),
    'knownGenres' => GameRepository::isAvailable() ? (new GameRepository())->listKnownGenres() : [],
    'saveError' => trim((string) ($_GET['error'] ?? '')),
]);
