<?php
/**
 * Enregistre la modification d’une fiche jeu catalogue (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\Csrf;
use Moncine\GameGenre;
use Moncine\GameRepository;
use Moncine\UploadLimits;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_POST);
$returnUrl = View::oeuvreJeuUrl(
    $oeuvreId,
    $catalogListContext->search(),
    $catalogListContext->sortBy(),
    $catalogListContext->sortDir(),
    $catalogListContext->page()
);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'cover_file' => 'Jaquette',
]);

Csrf::rejectUnlessValid($_POST, $returnUrl);

if ($oeuvreId <= 0) {
    header('Location: ' . $returnUrl . '&save_error=' . rawurlencode('Œuvre invalide.'));
    exit;
}

$repo = new GameRepository();
$editions = GameRepository::editionPayloadFromPost($_POST);

$result = $repo->updateCatalogByOeuvreId($oeuvreId, array_merge([
    'titre' => (string) ($_POST['titre'] ?? ''),
    'titre_original' => (string) ($_POST['titre_original'] ?? ''),
    'annee' => (int) ($_POST['annee'] ?? 0),
    'studio' => (string) ($_POST['studio'] ?? ''),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'genre' => GameGenre::normalizeFromPost($_POST['genres'] ?? []),
    'franchise' => (string) ($_POST['franchise'] ?? ''),
    'game_mode' => GameGenre::normalizeInput((string) ($_POST['game_mode'] ?? '')),
    'theme' => GameGenre::normalizeInput((string) ($_POST['theme'] ?? '')),
    'alternative_names' => GameGenre::normalizeInput((string) ($_POST['alternative_names'] ?? '')),
    'platform' => (string) ($_POST['platform'] ?? ''),
    'synopsis' => (string) ($_POST['synopsis'] ?? ''),
    'poster_url' => (string) ($_POST['poster_url'] ?? ''),
    'is_extension' => !empty($_POST['is_extension']),
    'base_game_oeuvre_id' => (int) ($_POST['base_game_oeuvre_id'] ?? 0),
    'is_remake' => !empty($_POST['is_remake']),
    'original_game_oeuvre_id' => (int) ($_POST['original_game_oeuvre_id'] ?? 0),
], $editions));

if ($result !== true) {
    header('Location: ' . $returnUrl . '&edit=1&save_error=' . rawurlencode((string) $result));
    exit;
}

$uploadedBinary = null;
if (isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $uploadedBinary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
}

$posterUrlInput = trim((string) ($_POST['poster_url'] ?? ''));
if ($uploadedBinary !== null || $posterUrlInput !== '') {
    $repo->savePoster($oeuvreId, $posterUrlInput, $uploadedBinary);
}

header('Location: ' . $returnUrl . '&saved=1');
exit;
