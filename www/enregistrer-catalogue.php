<?php
/**
 * Enregistre une nouvelle œuvre dans le catalogue partagé (sans bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\FilmEnricher;
use Moncine\FilmManualEdit;
use Moncine\GameRepository;
use Moncine\MediaDomain;
use Moncine\MoncineContentKind;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

$backUrl = '/catalogue.php';
$search = trim((string) ($_POST['catalog_q'] ?? ''));
if ($search !== '') {
    $backUrl .= '?q=' . rawurlencode($search);
}

$withEnrich = ((string) ($_POST['save_mode'] ?? 'save')) === 'enrich';
$contentKind = (string) ($_POST['content_kind'] ?? '');

Csrf::rejectUnlessValid($_POST, $backUrl);

$sep = str_contains($backUrl, '?') ? '&' : '?';

$redirectToNewOeuvre = static function (
    int $oeuvreId,
    string $mediaDomain,
    string $catalogSearch,
    array $extraParams = []
): void {
    $url = View::catalogOeuvreDetailUrl($oeuvreId, $mediaDomain, $catalogSearch);
    $params = array_merge(['added' => '1'], $extraParams);
    header('Location: ' . View::urlWithQuery($url, $params));
    exit;
};

if (MoncineContentKind::isJeuVideoFormValue($contentKind)) {
    if (!GameRepository::isAvailable()) {
        header('Location: ' . $backUrl . $sep . 'save_error=' . rawurlencode('Module jeux non disponible.'));
        exit;
    }

    $oeuvreId = (new CatalogAdmin())->createGameOeuvre(GameRepository::catalogPayloadFromPost($_POST));
    if (!is_int($oeuvreId)) {
        header('Location: ' . $backUrl . $sep . 'save_error=' . rawurlencode((string) $oeuvreId));
        exit;
    }

    $redirectToNewOeuvre($oeuvreId, MediaDomain::JEU, $search);
}

$parsed = FilmManualEdit::parseFromPost($_POST);
if (!$parsed['ok']) {
    header('Location: ' . $backUrl . $sep . 'save_error=' . rawurlencode($parsed['error']));
    exit;
}

$oeuvreId = (new CatalogAdmin())->createOeuvre($parsed['data']);
if (!is_int($oeuvreId)) {
    header('Location: ' . $backUrl . $sep . 'save_error=' . rawurlencode((string) $oeuvreId));
    exit;
}

if ($withEnrich) {
    $params = [];
    if (!FilmEnricher::canEnrich()) {
        $params['enrich'] = 'error';
        $params['enrich_msg'] = 'Clé API TMDB manquante. Configurez-la sur la page Importer.';
    } else {
        $enrichResult = (new FilmEnricher())->enrichOeuvre($oeuvreId);
        $params['enrich'] = $enrichResult['ok']
            ? 'ok'
            : ($enrichResult['not_found'] ? 'not_found' : 'error');
        $params['enrich_msg'] = $enrichResult['message'];
    }

    $redirectToNewOeuvre($oeuvreId, MediaDomain::FILM, $search, $params);
}

$redirectToNewOeuvre($oeuvreId, MediaDomain::FILM, $search);
