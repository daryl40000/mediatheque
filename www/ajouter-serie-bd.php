<?php
/**
 * Formulaire : nouvelle série BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext('/ajouter-serie-bd.php');

$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$catalogAddedMessage = '';
if (isset($_GET['added_series']) && (string) $_GET['added_series'] === '1') {
    $catalogAddedMessage = 'La série a été ajoutée à vos BD.';
}

View::render('ajouter-serie-bd', [
    'pageTitle' => $statut === LibraryStatut::WISHLIST ? 'Ajouter une envie BD' : 'Ajouter une série BD',
    'statut' => $statut,
    'kindChoices' => BdKind::choices(),
    'moduleAvailable' => BdRepository::isAvailable(),
    'catalogAddedMessage' => $catalogAddedMessage,
    'series' => [
        'titre' => trim((string) ($_GET['titre'] ?? '')),
        'kind' => BdKind::BD,
        'editeur' => '',
        'notes' => '',
    ],
    'error' => (string) ($_GET['error'] ?? ''),
]);
