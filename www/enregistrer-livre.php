<?php
/**
 * Enregistre un livre (catalogue + bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres.php');
    exit;
}

MediaDomainGuards::ensureLivreContext('/ajouter-livre.php');

$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = '/ajouter-livre.php?statut=' . rawurlencode($statut);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'cover_file' => 'Couverture',
    'back_cover_file' => '4e de couverture',
]);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();

$result = $repo->createWithLibrary([
    'titre' => (string) ($_POST['titre'] ?? ''),
    'sous_titre' => (string) ($_POST['sous_titre'] ?? ''),
    'auteur' => (string) ($_POST['auteur'] ?? ''),
    'annee' => (int) ($_POST['annee'] ?? 0),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'isbn' => (string) ($_POST['isbn'] ?? ''),
    'pages' => (int) ($_POST['pages'] ?? 0),
    'categories' => $_POST['categories'] ?? [],
    'langue' => (string) ($_POST['langue'] ?? 'fr'),
    'collection_label' => (string) ($_POST['collection_label'] ?? ''),
    'saga' => (string) ($_POST['saga'] ?? ''),
    'saga_ordre' => (int) ($_POST['saga_ordre'] ?? 0),
    'synopsis' => (string) ($_POST['synopsis'] ?? ''),
    'support_physique' => (string) ($_POST['support_physique'] ?? 'papier'),
    'game_oeuvre_ids' => $_POST['game_oeuvre_ids'] ?? [],
], $statut, $userId, $foyerId);

if (!is_int($result)) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

$bibId = $result;
$book = $repo->findByBibId($bibId, $userId, $foyerId);
$oeuvreId = (int) ($book['oeuvre_id'] ?? 0);

$uploadedBinary = null;
if ($oeuvreId > 0 && isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $uploadedBinary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
}

if ($oeuvreId > 0) {
    $repo->savePoster($oeuvreId, (string) ($_POST['poster_url'] ?? ''), $uploadedBinary);
}

$backBinary = null;
if ($oeuvreId > 0 && isset($_FILES['back_cover_file']) && (int) ($_FILES['back_cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $backBinary = (string) file_get_contents((string) $_FILES['back_cover_file']['tmp_name']);
}

if ($oeuvreId > 0) {
    $repo->saveBackCover($oeuvreId, (string) ($_POST['back_cover_url'] ?? ''), $backBinary);
}

header('Location: ' . View::livreUrl($bibId) . '&added=1');
exit;
