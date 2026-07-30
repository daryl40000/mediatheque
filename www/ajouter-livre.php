<?php
/**
 * Ajouter un livre (collection ou envies).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\LivreCategory;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext('/ajouter-livre.php');

$statutRaw = trim((string) ($_GET['statut'] ?? ''));
$showChoice = $statutRaw === '';
$statut = $showChoice ? '' : LibraryStatut::normalize($statutRaw);

View::render('ajouter-livre', [
    'pageTitle' => $showChoice ? 'Ajouter un livre' : 'Ajouter — ' . LibraryStatut::label($statut),
    'showChoice' => $showChoice,
    'statut' => $statut,
    'statutLabel' => $showChoice ? '' : LibraryStatut::label($statut),
    'moduleAvailable' => LivreRepository::isAvailable(),
    'knownCategories' => LivreCategory::suggestionLabels(),
    'sagaSuggestions' => LivreRepository::isAvailable()
        ? (new LivreRepository())->listKnownSagas()
        : [],
    'saveError' => trim((string) ($_GET['error'] ?? '')),
    'book' => [
        'titre' => '',
        'sous_titre' => '',
        'auteur' => '',
        'annee' => 0,
        'editeur' => '',
        'isbn' => '',
        'pages' => 0,
        'categories' => '',
        'langue' => 'fr',
        'collection_label' => '',
        'saga' => '',
        'saga_ordre' => 0,
        'synopsis' => '',
        'support_physique' => 'papier',
        'back_cover_url' => '',
    ],
]);
