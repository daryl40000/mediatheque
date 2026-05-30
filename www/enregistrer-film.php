<?php
/**
 * Enregistre un nouveau film (collection ou wishlist).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmEnricher;
use Moncine\FilmManualEdit;
use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ajouter-film.php');
    exit;
}

$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$backUrl = View::addFilmUrl($statut);
$withEnrich = ((string) ($_POST['save_mode'] ?? 'save')) === 'enrich'
    && UserContext::canManageCatalog();

Csrf::rejectUnlessValid($_POST, $backUrl);

$parsed = FilmManualEdit::parseFromPost($_POST);
if (!$parsed['ok']) {
    header('Location: ' . $backUrl . '&save_error=' . rawurlencode($parsed['error']));
    exit;
}

$filmId = (new FilmRepository())->createManual($parsed['data'], $statut);
if (!is_int($filmId)) {
    header('Location: ' . $backUrl . '&save_error=' . rawurlencode((string) $filmId));
    exit;
}

$params = [
    'added' => '1',
    'from_statut' => $statut,
];

if ($withEnrich) {
    if (!FilmEnricher::canEnrich()) {
        $params['enrich'] = 'error';
        $params['enrich_msg'] = 'Clé API TMDB manquante. Configurez-la sur la page Importer.';
    } else {
        $enrichResult = (new FilmEnricher())->enrichOne($filmId);
        $params['enrich'] = $enrichResult['ok']
            ? 'ok'
            : ($enrichResult['not_found'] ? 'not_found' : 'error');
        $params['enrich_msg'] = $enrichResult['message'];
    }
}

header('Location: /film.php?id=' . $filmId . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
