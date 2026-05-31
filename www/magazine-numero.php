<?php
/**
 * Fiche d’un numéro de magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$issue = $bibId > 0 ? $repo->findIssueByBibId($bibId, $userId, $foyerId) : null;

if ($issue === null) {
    View::render('magazine-numero', [
        'pageTitle' => 'Numéro introuvable',
        'issue' => null,
        'saved' => false,
        'error' => '',
    ]);
    http_response_code(404);
    exit;
}

$saved = isset($_GET['saved']);
$error = (string) ($_GET['error'] ?? '');

View::render('magazine-numero', [
    'pageTitle' => (string) ($issue['titre'] ?? 'Numéro'),
    'issue' => $issue,
    'saved' => $saved,
    'error' => $error,
    'dateLabel' => PublicationType::formatParutionDate(
        (string) ($issue['date_parution'] ?? ''),
        (string) ($issue['publication_type'] ?? '')
    ),
    'pdfUrl' => (int) ($issue['stored_object_id'] ?? 0) > 0
        ? '/media-object.php?id=' . (int) $issue['stored_object_id']
        : '',
]);
