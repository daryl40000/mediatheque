<?php
/**
 * Enregistre une lecture (aujourd’hui ou date passée).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\HistoriqueRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext();

$albumId = (int) ($_POST['album_id'] ?? 0);
$return = (string) ($_POST['return'] ?? '');
$dateRaw = (string) ($_POST['date_vue'] ?? '');
$noteRaw = (string) ($_POST['note'] ?? '');

if ($albumId <= 0) {
    header('Location: /bd.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$albumUrl = View::bdUrl($albumId);

$redirectError = static function (string $message) use ($return, $albumUrl): void {
    if ($return === 'album') {
        header('Location: ' . $albumUrl . '&lu_error=' . rawurlencode($message));
        exit;
    }
    header('Location: /bd.php?lu_error=' . rawurlencode($message));
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

$album = (new BdRepository())->findByBibId($albumId, $userId, $foyerId);
if ($album === null) {
    $redirectError('Album introuvable.');
}

$parsedDate = HistoriqueRepository::parseDateVueInput($dateRaw);
if (!$parsedDate['ok']) {
    $redirectError($parsedDate['error']);
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

try {
    (new HistoriqueRepository())->recordViewing($albumId, $parsedDate['date'], $parsedNote['note']);
} catch (\Throwable $e) {
    $redirectError('Impossible d’enregistrer la lecture. Réessayez.');
}

$params = [
    'lu' => '1',
    'lu_date' => HistoriqueRepository::formatDateVue($parsedDate['date']),
];
if ($parsedNote['note'] !== null) {
    $params['lu_note'] = (string) $parsedNote['note'];
}

if ($return === 'album') {
    header('Location: ' . $albumUrl . '&' . http_build_query($params));
    exit;
}

header('Location: /bd.php?lu=1');
exit;
