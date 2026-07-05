<?php
/**
 * Fusion manuelle de deux fiches catalogue (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\CatalogMaintenance;
use Moncine\Csrf;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$keepId = (int) ($_POST['keep_id'] ?? 0);
$removeId = (int) ($_POST['remove_id'] ?? 0);
$currentOeuvreId = (int) ($_POST['current_oeuvre_id'] ?? 0);
$catalogSearch = trim((string) ($_POST['catalog_q'] ?? ''));
$catalogSort = (string) ($_POST['catalog_sort'] ?? 'titre');
$catalogDir = (string) ($_POST['catalog_dir'] ?? 'asc');
$catalogPage = max(1, (int) ($_POST['catalog_page'] ?? 1));

$oeuvres = new OeuvreRepository();
$fallbackOeuvreId = $currentOeuvreId > 0 ? $currentOeuvreId : ($keepId > 0 ? $keepId : $removeId);
$fallbackOeuvre = $fallbackOeuvreId > 0 ? $oeuvres->findByIdForAdmin($fallbackOeuvreId) : null;
$fallbackDomain = $fallbackOeuvre !== null
    ? MediaDomain::normalize((string) ($fallbackOeuvre['media_domain'] ?? MediaDomain::FILM))
    : MediaDomain::FILM;

$errorReturnUrl = $fallbackOeuvreId > 0
    ? View::catalogOeuvreDetailUrl($fallbackOeuvreId, $fallbackDomain, $catalogSearch, $catalogSort, $catalogDir, $catalogPage)
    : View::catalogueUrl($catalogSearch, $catalogSort, $catalogDir, $catalogPage);

Csrf::rejectUnlessValid($_POST, $errorReturnUrl);

$appendQuery = static function (string $url, array $params): string {
    $sep = str_contains($url, '?') ? '&' : '?';

    return $url . $sep . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

if ($keepId <= 0 || $removeId <= 0) {
    header('Location: ' . $appendQuery($errorReturnUrl, [
        'merge_error' => 'Choisissez une autre fiche à fusionner.',
    ]));
    exit;
}

$adminUserId = Auth::currentUserId();
$result = (new CatalogMaintenance())->mergeOeuvres($keepId, $removeId, $adminUserId);

if ($result !== true) {
    header('Location: ' . $appendQuery($errorReturnUrl, [
        'merge_error' => (string) $result,
    ]));
    exit;
}

$keepOeuvre = $oeuvres->findByIdForAdmin($keepId);
$keepDomain = $keepOeuvre !== null
    ? MediaDomain::normalize((string) ($keepOeuvre['media_domain'] ?? MediaDomain::FILM))
    : $fallbackDomain;
$successUrl = View::catalogOeuvreDetailUrl($keepId, $keepDomain, $catalogSearch, $catalogSort, $catalogDir, $catalogPage);

header('Location: ' . $appendQuery($successUrl, [
    'merge_ok' => '1',
    'merge_removed' => (string) $removeId,
]));
exit;
