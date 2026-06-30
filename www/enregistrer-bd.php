<?php
/**
 * Enregistre un tome BD (catalogue + bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext();

$seriesId = (int) ($_POST['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = $seriesId > 0
    ? '/ajouter-tome-bd.php?series_id=' . $seriesId . '&statut=' . rawurlencode($statut)
    : '/ajouter-serie-bd.php';

Csrf::rejectUnlessValid($_POST, $returnUrl);

if ($seriesId <= 0) {
    header('Location: /ajouter-serie-bd.php?error=' . rawurlencode('Série obligatoire.'));
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();
$oeuvreIdFromCatalog = max(0, (int) ($_POST['oeuvre_id'] ?? 0));

if ($oeuvreIdFromCatalog > 0) {
    $catalog = $repo->findCatalogByOeuvreId($oeuvreIdFromCatalog);
    if ($catalog === null || (int) ($catalog['series_id'] ?? 0) !== $seriesId) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Tome catalogue invalide pour cette série.'));
        exit;
    }
    $result = $repo->addFromCatalogOeuvre($oeuvreIdFromCatalog, $statut, $userId, $foyerId, [
        'support_physique' => BdRepository::supportFromPost($_POST),
    ]);
} else {
    $tomeNumero = max(0, (int) ($_POST['tome_numero'] ?? 0));
    if ($tomeNumero > 0 && $repo->findCatalogTomeBySeriesAndNumero($seriesId, $tomeNumero) !== null) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode(
            'Ce tome existe déjà au catalogue pour cette série.'
        ));
        exit;
    }

    $result = $repo->createTomeWithLibrary($seriesId, [
        'titre' => (string) ($_POST['titre'] ?? ''),
        'annee' => (int) ($_POST['annee'] ?? 0),
        'synopsis' => (string) ($_POST['synopsis'] ?? ''),
        'tome_numero' => $tomeNumero,
        'tome_label' => (string) ($_POST['tome_label'] ?? ''),
        'scenariste' => (string) ($_POST['scenariste'] ?? ''),
        'dessinateur' => (string) ($_POST['dessinateur'] ?? ''),
        'editeur' => (string) ($_POST['editeur'] ?? ''),
        'genre' => (string) ($_POST['genre'] ?? ''),
        'support_physique' => BdRepository::supportFromPost($_POST),
    ], $statut, $userId, $foyerId);
}

if (!is_int($result)) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

$album = $repo->findByBibId($result, $userId, $foyerId);
$oeuvreId = (int) ($album['oeuvre_id'] ?? 0);

if (
    $oeuvreId > 0
    && isset($_FILES['cover_file'])
    && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
) {
    $binary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
    $posterUrl = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
    if ($posterUrl !== '') {
        $repo->updatePosterUrl($oeuvreId, $posterUrl);
    }
}

header('Location: ' . View::bdUrl($result) . '?saved=1');
exit;
