<?php
/**
 * Questionnaire pour choisir un film du soir.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\FilmRepository;
use Moncine\QuizSession;
use Moncine\View;

if (isset($_GET['reset'])) {
    QuizSession::clearExcluded();
}

$films = new FilmRepository();
$savedCriteria = QuizSession::load() ?? [
    'duree_film' => 'moyen',
    'content_kind' => '',
    'decennie' => '',
    'styles' => [],
    'nationalites' => [],
    'format_image' => '',
    'format_son' => '',
    'vu_policy' => 'ancien_ok',
];

View::render('quiz', [
    'pageTitle' => 'Questionnaire',
    'styleChoices' => $films->distinctStyles(),
    'selectedStyles' => $savedCriteria['styles'] ?? [],
    'nationaliteChoices' => $films->distinctNationalites(),
    'selectedNationalites' => $savedCriteria['nationalites'] ?? [],
    'saved' => $savedCriteria,
]);
