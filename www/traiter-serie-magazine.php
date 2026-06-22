<?php
/**
 * Retire une série magazine de la bibliothèque (collection ou envies).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /magazines.php');
    exit;
}

MediaDomainGuards::ensureMagazineContext();

$seriesId = (int) ($_POST['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_POST['return_statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = $seriesId > 0
    ? View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', ['statut' => $statut])
    : ($statut === LibraryStatut::WISHLIST ? '/magazines-envies.php' : '/magazines.php');

Csrf::rejectUnlessValid($_POST, $returnUrl);

$action = (string) ($_POST['action'] ?? '');
if ($action !== 'remove_series') {
    header('Location: ' . $returnUrl);
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$result = (new MagazineRepository())->removeSeriesFromLibrary($seriesId, $statut, $userId, $foyerId);

if (!is_array($result)) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

$redirect = $statut === LibraryStatut::WISHLIST ? '/magazines-envies.php' : '/magazines.php';
$params = [
    'series_removed' => '1',
    'removed_issues' => (string) (int) ($result['removed_issues'] ?? 0),
];
header('Location: ' . $redirect . '?' . http_build_query($params));
exit;
