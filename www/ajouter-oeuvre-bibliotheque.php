<?php
/**
 * Ajoute une œuvre du catalogue à Mes films ou Mes envies sans formulaire intermédiaire.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ajouter-film.php');
    exit;
}

$oeuvreId = max(0, (int) ($_POST['oeuvre_id'] ?? 0));
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$backUrl = View::addFilmChoiceUrl($oeuvreId);

Csrf::rejectUnlessValid($_POST, $backUrl);

if ($oeuvreId <= 0) {
    header('Location: ' . $backUrl . '&save_error=' . rawurlencode('Œuvre invalide.'));
    exit;
}

$filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, $statut);
if (!is_int($filmId)) {
    header('Location: ' . $backUrl . '&save_error=' . rawurlencode((string) $filmId));
    exit;
}

$params = [
    'added' => '1',
    'from_statut' => $statut,
];
header('Location: /film.php?id=' . $filmId . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
exit;
