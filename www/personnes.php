<?php
/**
 * Recherche de films par réalisateur ou acteur principal.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\FilmRepository;
use Moncine\View;

$repo = new FilmRepository();
$query = trim((string) ($_GET['q'] ?? ''));
$films = [];
$searched = $query !== '';

if ($searched) {
    $raw = $repo->findByPersonne($query);
    foreach ($raw as $film) {
        $film['roles'] = FilmRepository::rolesForPerson($film, $query);
        $films[] = $film;
    }
}

View::render('personnes', [
    'pageTitle' => 'Par acteur ou réalisateur',
    'query' => $query,
    'films' => $films,
    'searched' => $searched,
    'suggestions' => $repo->distinctPersonnes(),
]);
