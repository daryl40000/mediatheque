<?php
/**
 * Ajouter un film : choix collection / wishlist, puis formulaire.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\OeuvreRepository;
use Moncine\UserContext;
use Moncine\View;

$repo = new FilmRepository();
$statutRaw = trim((string) ($_GET['statut'] ?? ''));
$showChoice = $statutRaw === '';

if (!$showChoice) {
    $statut = LibraryStatut::normalize($statutRaw);
    if ($statut === LibraryStatut::WISHLIST && !$repo->usesCatalogModel()) {
        header('Location: /films.php');
        exit;
    }
} else {
    $statut = '';
}

$sagaSuggestions = $repo->usesCatalogModel()
    ? $repo->listKnownSagas()
    : $repo->distinctSagas();
$prefillOeuvreId = max(0, (int) ($_GET['oeuvre_id'] ?? 0));
$prefillFilm = null;
if ($prefillOeuvreId > 0 && $repo->usesCatalogModel()) {
    $prefillFilm = (new OeuvreRepository())->findById($prefillOeuvreId);
}

View::render('ajouter-film', [
    'pageTitle' => $showChoice ? 'Ajouter un film' : 'Ajouter — ' . LibraryStatut::label($statut),
    'showChoice' => $showChoice,
    'statut' => $statut,
    'statutLabel' => $showChoice ? '' : LibraryStatut::label($statut),
    'sagaSuggestions' => $sagaSuggestions,
    'usesCatalog' => $repo->usesCatalogModel(),
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'hasTmdbKey' => \Moncine\FilmEnricher::canEnrich(),
    'canManageCatalog' => UserContext::canManageCatalog(),
    'prefillOeuvreId' => $prefillFilm !== null ? $prefillOeuvreId : 0,
    'prefillFilm' => $prefillFilm,
]);
