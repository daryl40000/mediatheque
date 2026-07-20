<?php
/**
 * Retire une série BD de la bibliothèque (collection ou envies).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext();

$seriesId = (int) ($_POST['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_POST['return_statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = $seriesId > 0
    ? View::bdSeriesUrl($seriesId, 'tome', 'asc', ['statut' => $statut])
    : ($statut === LibraryStatut::WISHLIST ? '/bd-envies.php' : '/bd.php');

Csrf::rejectUnlessValid($_POST, $returnUrl);

$action = (string) ($_POST['action'] ?? '');
if ($action !== 'remove_series') {
    header('Location: ' . $returnUrl);
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$result = (new BdRepository())->removeSeriesFromLibrary($seriesId, $statut, $userId, $foyerId);

if (!is_array($result)) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?')
        . 'error=' . rawurlencode((string) $result));
    exit;
}

$redirect = $statut === LibraryStatut::WISHLIST ? '/bd-envies.php' : '/bd.php';
$params = [
    'series_removed' => '1',
    'removed_tomes' => (string) (int) ($result['removed_tomes'] ?? 0),
];
header('Location: ' . $redirect . '?' . http_build_query($params));
exit;
