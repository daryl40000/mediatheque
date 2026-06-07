<?php
/**
 * Formulaire : nouvelle série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/ajouter-serie-magazine.php');

View::render('ajouter-serie-magazine', [
    'pageTitle' => 'Nouvelle série magazine',
    'publicationTypes' => PublicationType::choices(),
    'series' => [
        'titre' => trim((string) ($_GET['titre'] ?? '')),
        'publication_type' => 'mensuel',
        'tags' => '',
        'editeur' => '',
        'issn' => '',
        'langue' => 'fr',
        'pays' => '',
        'date_debut' => '',
        'date_fin' => '',
        'notes' => '',
    ],
    'error' => (string) ($_GET['error'] ?? ''),
]);
