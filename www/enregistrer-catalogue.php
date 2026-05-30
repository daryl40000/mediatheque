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

Csrf::rejectUnlessValid($_POST, $backUrl);

$parsed = FilmManualEdit::parseFromPost($_POST);
if (!$parsed['ok']) {
    header('Location: ' . $backUrl . (str_contains($backUrl, '?') ? '&' : '?') . 'save_error=' . rawurlencode($parsed['error']));
    exit;
}

$oeuvreId = (new CatalogAdmin())->createOeuvre($parsed['data']);
if (!is_int($oeuvreId)) {
    header('Location: ' . $backUrl . (str_contains($backUrl, '?') ? '&' : '?') . 'save_error=' . rawurlencode((string) $oeuvreId));
    exit;
}

$sep = str_contains($backUrl, '?') ? '&' : '?';

if ($withEnrich) {
    $params = ['added' => '1'];
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

    $oeuvreUrl = View::oeuvreUrl($oeuvreId, $search);
    $urlSep = str_contains($oeuvreUrl, '?') ? '&' : '?';
    header('Location: ' . $oeuvreUrl . $urlSep . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    exit;
}

header('Location: ' . $backUrl . $sep . 'added=1');
exit;
