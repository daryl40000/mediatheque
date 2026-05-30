<?php
/**
 * Liste des films les mieux notés pendant la session de choix.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\ChoixNote;
use Moncine\FilmRepository;
use Moncine\QuizSession;
use Moncine\View;

if (!QuizSession::hasRatings()) {
    header('Location: /quiz.php');
    exit;
}

$repo = new FilmRepository();
$topScore = QuizSession::getTopScore();
$entries = [];

foreach (QuizSession::getRatingsSorted() as $row) {
    $film = $repo->findById($row['film_id']);
    if ($film === null) {
        continue;
    }
    $entries[] = [
        'film' => $film,
        'note_key' => $row['note_key'],
        'label' => $row['label'],
        'score' => $row['score'],
        'is_top' => $topScore !== null && $row['score'] === $topScore,
    ];
}

View::render('meilleurs', [
    'pageTitle' => 'Mieux notés',
    'entries' => $entries,
    'noteLevels' => ChoixNote::LEVELS,
]);
