<?php
/**
 * Fiche d’un numéro magazine sur le profil public (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\UserProfile;
use Moncine\UserPublicProfileService;
use Moncine\View;

$viewerId = Auth::currentUserId();
if ($viewerId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$targetUserId = max(0, (int) ($_GET['id'] ?? 0));
$bibId = max(0, (int) ($_GET['bib_id'] ?? 0));

$profile = new UserPublicProfileService();
$access = $profile->canViewMagazineIssue($viewerId, $targetUserId, $bibId);

if ($access !== true) {
    http_response_code($bibId <= 0 || str_contains((string) $access, 'introuvable') ? 404 : 403);
    View::render('utilisateur-numero-magazine', [
        'pageTitle' => 'Numéro magazine',
        'profileUser' => null,
        'accessDenied' => (string) $access,
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::MAGAZINE,
        'pageMediaDomain' => MediaDomain::MAGAZINE,
        'issue' => null,
    ]);
    exit;
}

$user = $profile->findPublicUser($targetUserId);
$issue = $profile->findMagazineIssueForProfile($targetUserId, $bibId);
if ($user === null || $issue === null) {
    http_response_code(404);
    View::render('utilisateur-numero-magazine', [
        'pageTitle' => 'Numéro introuvable',
        'profileUser' => null,
        'accessDenied' => 'Numéro introuvable.',
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::MAGAZINE,
        'pageMediaDomain' => MediaDomain::MAGAZINE,
        'issue' => null,
    ]);
    exit;
}

$displayName = UserProfile::displayName($user);
$pageStatut = (string) ($issue['statut'] ?? \Moncine\LibraryStatut::COLLECTION);
$listMode = $pageStatut === \Moncine\LibraryStatut::WISHLIST ? 'envies' : 'collection';

View::render('utilisateur-numero-magazine', [
    'pageTitle' => (string) ($issue['series_titre'] ?? 'Numéro') . ' n°' . (string) ($issue['numero'] ?? '') . ' — ' . $displayName,
    'profileUser' => $user,
    'accessDenied' => '',
    'targetUserId' => $targetUserId,
    'profileDomain' => MediaDomain::MAGAZINE,
    'pageMediaDomain' => MediaDomain::MAGAZINE,
    'issue' => $issue,
    'listMode' => $listMode,
    'dateLabel' => PublicationType::formatParutionDate(
        (string) ($issue['date_parution'] ?? ''),
        (string) ($issue['publication_type'] ?? '')
    ),
]);
