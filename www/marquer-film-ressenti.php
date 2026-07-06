<?php
/**
 * Enregistre la note personnelle sur un film (sans date).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmListContext;
use Moncine\FilmRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /films.php');
    exit;
}

$filmId = (int) ($_POST['film_id'] ?? 0);
$noteRaw = (string) ($_POST['note'] ?? '');

if ($filmId <= 0) {
    header('Location: /films.php');
    exit;
}

$film = (new FilmRepository())->findById($filmId);
$defaultList = $film !== null && ($film['statut'] ?? '') === LibraryStatut::WISHLIST
    ? FilmListContext::WISHLIST
    : FilmListContext::COLLECTION;
$listContext = FilmListContext::fromPost($_POST, $defaultList);

$redirectError = static function (string $message) use ($filmId, $listContext): void {
    header('Location: ' . $listContext->filmUrlWithQuery($filmId, [
        'note_error' => $message,
        'popover' => 'note',
    ]));
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

if ($film === null || ($film['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Ce film est introuvable ou encore dans vos envies.');
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

if ($parsedNote['note'] === null) {
    $redirectError('Choisissez un ressenti parmi les cinq proposés.');
}

try {
    (new HistoriqueRepository())->setPersonalNote($filmId, $parsedNote['note']);
} catch (\Throwable $e) {
    $message = $e->getMessage();
    if ($message === '' || str_contains($message, 'SQLSTATE')) {
        $message = 'Impossible d’enregistrer la note. Rechargez la page et réessayez.';
    }
    $redirectError($message);
}

header('Location: ' . $listContext->filmUrlWithQuery($filmId, [
    'note' => (string) (int) $parsedNote['note'],
]));
exit;
