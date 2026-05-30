<?php
/**
 * Liste des propositions catalogue de l’utilisateur connecté.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\UserContext;
use Moncine\View;

Auth::enforceWebAccess();
CatalogSubmission::denyUnlessSubmitter();

$submissions = (new CatalogSubmission())->listForUser(UserContext::currentUserId());

View::render('mes-soumissions', [
    'pageTitle' => 'Mes propositions',
    'submissions' => $submissions,
    'submitted' => isset($_GET['submitted']) && (string) $_GET['submitted'] === '1',
]);
