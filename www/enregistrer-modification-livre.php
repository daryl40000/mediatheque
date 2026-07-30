<?php
/**
 * Enregistre la modification d’un livre.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres.php');
    exit;
}

MediaDomainGuards::ensureLivreContext();

$bibId = (int) ($_POST['book_id'] ?? 0);
$returnFiche = (string) ($_POST['return'] ?? '') === 'fiche';
$editUrl = View::livreEditUrl($bibId);
$ficheUrl = View::livreUrl($bibId);

// En cas d’erreur depuis le popover de la fiche, on y revient avec le message.
$errorRedirect = static function (string $message) use ($returnFiche, $ficheUrl, $editUrl): void {
    if ($returnFiche) {
        header('Location: ' . $ficheUrl . '&edit_error=' . rawurlencode($message) . '&popover=edit');
        exit;
    }
    header('Location: ' . $editUrl . '&error=' . rawurlencode($message));
    exit;
};

UploadLimits::guardPostWithFiles($_POST, $returnFiche ? $ficheUrl . '&popover=edit' : $editUrl, [
    'cover_file' => 'Couverture',
    'back_cover_file' => '4e de couverture',
]);

Csrf::rejectUnlessValid($_POST, $returnFiche ? $ficheUrl . '&popover=edit' : $editUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();

$result = $repo->updateByBibId($bibId, [
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
], $userId, $foyerId);

if ($result !== true) {
    $errorRedirect((string) $result);
}

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

header('Location: ' . View::livreUrl($bibId) . '&saved=1');
exit;
