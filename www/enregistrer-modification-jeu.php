<?php
/**
 * Enregistre la modification d’un jeu (administrateurs).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameGenre;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

MediaDomainGuards::ensureGameContext();

if (!UserContext::canManageCatalog()) {
    header('Location: /jeux.php');
    exit;
}

$bibId = (int) ($_POST['bib_id'] ?? 0);
$returnUrl = View::gameEditUrl($bibId);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'cover_file' => 'Jaquette',
]);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

$editions = GameRepository::editionPayloadFromPost($_POST);
$linuxFlags = GameRepository::linuxFlagsFromPost($_POST);

$result = $repo->updateCatalog($bibId, array_merge([
    'titre' => (string) ($_POST['titre'] ?? ''),
    'annee' => (int) ($_POST['annee'] ?? 0),
    'studio' => (string) ($_POST['studio'] ?? ''),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'genre' => GameGenre::normalizeFromPost($_POST['genres'] ?? []),
    'platform' => (string) ($_POST['platform'] ?? ''),
    'synopsis' => (string) ($_POST['synopsis'] ?? ''),
    'is_extension' => !empty($_POST['is_extension']),
    'base_game_oeuvre_id' => (int) ($_POST['base_game_oeuvre_id'] ?? 0),
    'tested_on_linux' => $linuxFlags['tested_on_linux'],
    'linux_not_supported' => $linuxFlags['linux_not_supported'],
], $editions), $userId, $foyerId);

if ($result !== true) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

$game = $repo->findByBibId($bibId, $userId, $foyerId);
$oeuvreId = (int) ($game['oeuvre_id'] ?? 0);

$uploadedBinary = null;
if ($oeuvreId > 0 && isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $uploadedBinary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
}

$posterUrlInput = trim((string) ($_POST['poster_url'] ?? ''));
if ($oeuvreId > 0 && ($uploadedBinary !== null || $posterUrlInput !== '')) {
    $repo->savePoster($oeuvreId, $posterUrlInput, $uploadedBinary);
}

header('Location: ' . View::gameUrl($bibId) . '&saved=1');
exit;
