<?php
/**
 * Formulaire : proposer une œuvre au catalogue (utilisateur connecté).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\CatalogSubmissionRepository;
use Moncine\UserContext;
use Moncine\View;

Auth::enforceWebAccess();
CatalogSubmission::denyUnlessSubmitter();

$userId = UserContext::currentUserId();
$repo = new CatalogSubmissionRepository();
$pending = $repo->listForUser($userId, CatalogSubmissionRepository::STATUS_PENDING);

View::render('proposer-oeuvre', [
    'pageTitle' => 'Proposer au catalogue',
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'pendingCount' => count($pending),
]);
