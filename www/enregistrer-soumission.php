<?php
/**
 * Enregistre une proposition d’œuvre au catalogue.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\Csrf;
use Moncine\FilmManualEdit;
use Moncine\UserContext;

Auth::enforceWebAccess();
CatalogSubmission::denyUnlessSubmitter();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /proposer-oeuvre.php');
    exit;
}

$backUrl = '/proposer-oeuvre.php';
Csrf::rejectUnlessValid($_POST, $backUrl);

$parsed = FilmManualEdit::parseFromPost($_POST);
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
