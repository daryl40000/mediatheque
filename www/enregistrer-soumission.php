<?php
/**
 * Enregistre une proposition d’œuvre au catalogue (film ou jeu).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\Csrf;
use Moncine\FilmManualEdit;
use Moncine\GameManualEdit;
use Moncine\GameRepository;
use Moncine\MediaDomain;
use Moncine\UserContext;

Auth::enforceWebAccess();
CatalogSubmission::denyUnlessSubmitter();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /proposer-oeuvre.php');
    exit;
}

$domain = MediaDomain::normalize((string) ($_POST['submission_domain'] ?? MediaDomain::FILM));
$backUrl = MediaDomain::isGame($domain) ? '/proposer-jeu.php' : '/proposer-oeuvre.php';

Csrf::rejectUnlessValid($_POST, $backUrl);

if (MediaDomain::isGame($domain)) {
    if (!GameRepository::isAvailable()) {
        header('Location: ' . $backUrl . '?save_error=' . rawurlencode('Module jeux non disponible.'));
        exit;
    }
    $parsed = GameManualEdit::parseFromPost($_POST);
} else {
    $parsed = FilmManualEdit::parseFromPost($_POST);
    if ($parsed['ok']) {
        $parsed['data']['submission_domain'] = MediaDomain::FILM;
    }
}

if (!$parsed['ok']) {
    header('Location: ' . $backUrl . '?save_error=' . rawurlencode($parsed['error']));
    exit;
}

$userNote = trim((string) ($_POST['user_note'] ?? ''));
$result = (new CatalogSubmission())->submit(
    UserContext::currentUserId(),
    $parsed['data'],
    $userNote
);

if (!is_int($result)) {
    header('Location: ' . $backUrl . '?save_error=' . rawurlencode((string) $result));
    exit;
}

header('Location: /mes-soumissions.php?submitted=1');
exit;
