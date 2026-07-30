<?php
/**
 * Formulaire : nouvelle série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\MagazineSeriesCategory;
use Moncine\MagazineRepository;
use Moncine\PublicationType;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/ajouter-serie-magazine.php');

$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$catalogAddedMessage = '';
if (isset($_GET['added_series']) && (string) $_GET['added_series'] === '1') {
    $catalogAddedMessage = $statut === LibraryStatut::WISHLIST
        ? 'La revue a été ajoutée à vos envies magazines.'
        : 'La revue a été ajoutée à vos magazines.';
}

View::render('ajouter-serie-magazine', [
    'pageTitle' => $statut === LibraryStatut::WISHLIST
        ? 'Ajouter une envie magazine'
        : 'Ajouter une série magazine',
    'statut' => $statut,
    'publicationTypes' => PublicationType::choices(),
    'moduleAvailable' => MagazineRepository::isAvailable(),
    'canManageCatalog' => CatalogAdmin::canAccess(),
    'catalogAddedMessage' => $catalogAddedMessage,
    'series' => [
        'titre' => trim((string) ($_GET['titre'] ?? '')),
        'publication_type' => 'mensuel',
        'tags' => '',
        'categories' => '',
        'editeur' => '',
        'issn' => '',
        'langue' => 'fr',
        'pays' => '',
        'date_debut' => '',
        'date_fin' => '',
        'notes' => '',
    ],
    'error' => (string) ($_GET['error'] ?? ''),
    'knownCategories' => MagazineSeriesCategory::suggestionLabels(),
]);
