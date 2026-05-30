<?php
/**
 * Liste des films par type de support physique (DVD, Blu-ray, Blu-ray 4K).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\FilmRepository;
use Moncine\SupportPhysique;
use Moncine\View;

$repo = new FilmRepository();
$type = SupportPhysique::normalize((string) ($_GET['type'] ?? ''));
$films = [];
$searched = SupportPhysique::isValid($type);

if ($searched) {
    $films = $repo->findBySupportPhysique($type);
}

View::render('support', [
    'pageTitle' => $searched ? 'Support ' . SupportPhysique::label($type) : 'Par support',
    'type' => $type,
    'typeLabel' => SupportPhysique::label($type),
    'films' => $films,
    'searched' => $searched,
    'availableTypes' => $repo->distinctSupportPhysique(),
]);
