<?php
/**
 * Affiche la proposition de film selon les réponses du questionnaire.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\ChoixNote;
use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\TmdbConfig;
use Moncine\UserContext;
use Moncine\QuizSession;
use Moncine\Recommender;
use Moncine\View;

$action = (string) ($_POST['action'] ?? '');
$forcedFilmId = (int) ($_GET['film_id'] ?? 0);
$repo = new FilmRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validateFromPost($_POST)) {
        header('Location: /quiz.php?csrf_error=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'retirage') {
    $criteria = QuizSession::load();
    if ($criteria === null) {
        header('Location: /quiz.php');
        exit;
    }
    $excludeId = (int) ($_POST['exclude_film_id'] ?? 0);
    if ($excludeId > 0) {
        QuizSession::addProposed($excludeId);
    }
    $forcedFilmId = 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $criteria = QuizSession::parseFromPost($_POST);
    QuizSession::save($criteria);
    QuizSession::clearExcluded();
    $forcedFilmId = 0;
} elseif ($forcedFilmId > 0 || isset($_GET['tirage'])) {
    $criteria = QuizSession::load();
    if ($criteria === null) {
        header('Location: /quiz.php');
        exit;
    }
    if (isset($_GET['tirage'])) {
        $forcedFilmId = 0;
    }
} else {
    header('Location: /quiz.php');
    exit;
}

$criteria['exclude_ids'] = QuizSession::getExcludeIdsForDraw();
$engine = new Recommender();

if ($forcedFilmId > 0) {
    $film = $repo->findById($forcedFilmId);
    $pick = $film !== null ? ['film' => $film, 'score' => 0] : null;
} else {
    $pick = $engine->pickOne($criteria);
    if ($pick !== null) {
        QuizSession::addProposed((int) $pick['film']['id']);
    }
}

$alternatives = [];
if ($pick !== null && $forcedFilmId === 0) {
    $altCriteria = $criteria;
    $altCriteria['exclude_ids'] = array_merge(
        $criteria['exclude_ids'],
        [(int) $pick['film']['id']]
    );
    $alternatives = $engine->recommend($altCriteria, 5, true);
    $alternatives = array_values(array_filter(
        $alternatives,
        static fn ($item) => (int) $item['film']['id'] !== (int) $pick['film']['id']
    ));
}

$noMoreFilms = $pick === null && QuizSession::getExcludeIdsForDraw() !== [];
$currentRating = null;
$currentRatingLabel = null;
if ($pick !== null) {
    $currentRating = QuizSession::getRating((int) $pick['film']['id']);
    if ($currentRating !== null) {
        $currentRatingLabel = ChoixNote::label($currentRating);
    }
}

$enrichStatus = null;
$enrichMessage = '';
if ($pick !== null && isset($_GET['enrich'])) {
    $enrichStatus = match ((string) $_GET['enrich']) {
        'ok' => 'ok',
        'not_found' => 'not_found',
        default => 'error',
    };
    $enrichMessage = (string) ($_GET['enrich_msg'] ?? '');
    $refreshed = $repo->findById((int) $pick['film']['id']);
    if ($refreshed !== null) {
        $pick['film'] = $refreshed;
    }
}

$sessionCriteria = QuizSession::load();
$decennieLabel = '';
$dureeFilmLabel = '';
$nationalitesLabel = '';
$contentKindLabel = '';
if ($sessionCriteria !== null) {
    if (($sessionCriteria['decennie'] ?? '') !== '') {
        $decennieLabel = QuizSession::decennieLabel((string) $sessionCriteria['decennie']);
    }
    $dureeKey = (string) ($sessionCriteria['duree_film'] ?? 'moyen');
    if (QuizSession::dureeFilmFiltersDuration($dureeKey)) {
        $dureeFilmLabel = QuizSession::dureeFilmLabel($dureeKey);
    }
    $nationalitesLabel = QuizSession::nationalitesSummary($sessionCriteria);
    $contentKindLabel = QuizSession::contentKindSummary($sessionCriteria);
}

View::render('resultat', [
    'pageTitle' => 'Proposition',
    'pick' => $pick,
    'alternatives' => $alternatives,
    'hasSession' => QuizSession::hasCriteria(),
    'decennieLabel' => $decennieLabel,
    'dureeFilmLabel' => $dureeFilmLabel,
    'nationalitesLabel' => $nationalitesLabel,
    'contentKindLabel' => $contentKindLabel,
    'hasRatings' => QuizSession::hasRatings(),
    'noMoreFilms' => $noMoreFilms,
    'currentRating' => $currentRating,
    'currentRatingLabel' => $currentRatingLabel,
    'noteLevels' => ChoixNote::LEVELS,
    'hasTmdbKey' => TmdbConfig::hasApiKey(),
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
    'returnPage' => 'resultat',
    'currentTmdbId' => $pick !== null ? (int) ($pick['film']['tmdb_id'] ?? 0) : 0,
    'currentTmdbMediaType' => $pick !== null ? (string) ($pick['film']['tmdb_media_type'] ?? '') : '',
    'currentTmdbTvKind' => $pick !== null ? (string) ($pick['film']['tmdb_tv_kind'] ?? '') : '',
    'filmId' => $pick !== null ? (int) $pick['film']['id'] : 0,
    'showTmdbEnrich' => UserContext::canManageCatalog(),
]);
