<?php
/**
 * Enregistre un jeu vidéo (catalogue + bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameGenre;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

MediaDomainGuards::ensureGameContext('/ajouter-jeu.php');

$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = '/ajouter-jeu.php?statut=' . rawurlencode($statut);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'cover_file' => 'Jaquette',
]);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

$editions = GameRepository::editionPayloadFromPost($_POST);
$linuxFlags = GameRepository::linuxFlagsFromPost($_POST);
$libraryDetails = array_merge($editions, $linuxFlags, [
    'platform' => (string) ($_POST['platform'] ?? ''),
]);

$oeuvreId = max(0, (int) ($_POST['oeuvre_id'] ?? 0));
if ($oeuvreId > 0) {
    $result = $repo->addFromCatalogOeuvre($oeuvreId, $statut, $userId, $foyerId, $libraryDetails);
} else {
    $result = $repo->createWithLibrary(array_merge([
        'titre' => (string) ($_POST['titre'] ?? ''),
        'annee' => (int) ($_POST['annee'] ?? 0),
        'studio' => (string) ($_POST['studio'] ?? ''),
        'editeur' => (string) ($_POST['editeur'] ?? ''),
        'genre' => GameGenre::normalizeFromPost($_POST['genres'] ?? []),
        'platform' => (string) ($_POST['platform'] ?? ''),
        'synopsis' => (string) ($_POST['synopsis'] ?? ''),
        'poster_url' => '',
        'is_extension' => !empty($_POST['is_extension']),
        'base_game_oeuvre_id' => (int) ($_POST['base_game_oeuvre_id'] ?? 0),
        'is_remake' => !empty($_POST['is_remake']),
        'original_game_oeuvre_id' => (int) ($_POST['original_game_oeuvre_id'] ?? 0),
        'tested_on_linux' => $linuxFlags['tested_on_linux'],
        'linux_not_supported' => $linuxFlags['linux_not_supported'],
    ], $editions), $statut, $userId, $foyerId);
}

if (!is_int($result)) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

$game = $repo->findByBibId($result, $userId, $foyerId);
$resolvedOeuvreId = (int) ($game['oeuvre_id'] ?? $oeuvreId);

$uploadedBinary = null;
if ($resolvedOeuvreId > 0 && isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $uploadedBinary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
}

if ($resolvedOeuvreId > 0 && $oeuvreId <= 0) {
    $repo->savePoster($resolvedOeuvreId, (string) ($_POST['poster_url'] ?? ''), $uploadedBinary);
}

header('Location: ' . View::gameUrl($result) . '&saved=1');
exit;
