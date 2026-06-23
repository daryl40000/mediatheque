<?php
/**
 * Création et révocation des liens de partage visiteur.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\AppUrl;
use Moncine\MediaDomain;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\ShareLinkSessionStore;
use Moncine\ShareLinkShare;
use Moncine\UserContext;
use Moncine\UserProfile;
use Moncine\View;

$userId = Auth::currentUserId();
$foyerId = UserContext::currentFoyerId();
$service = new ShareLinkService();
$repo = new ShareLinkRepository();

$flash = '';
$flashError = '';
$newShareUrl = '';
$newShareLinkId = 0;
$newShareScopeLabel = '';
$shareEmailOk = '';
$shareEmailError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/gerer-partages.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $scope = ShareLinkScope::normalize((string) ($_POST['scope'] ?? ''));
        $mediaDomain = MediaDomain::normalize((string) ($_POST['media_domain'] ?? MediaDomain::FILM));
        $label = trim((string) ($_POST['label'] ?? ''));
        $result = $service->create($userId, $foyerId, $scope, $label, null, $mediaDomain);
        if (is_string($result)) {
            $flashError = $result;
        } else {
            $rawToken = (string) $result['token'];
            $scopeNorm = ShareLinkScope::normalize((string) ($result['link']['scope'] ?? ''));
            $linkDomain = ShareLinkRepository::mediaDomainFromRow($result['link']);
            $newShareUrl = ShareLinkService::listUrl($rawToken, $scopeNorm, $linkDomain);
            $newShareLinkId = (int) ($result['link']['id'] ?? 0);
            $newShareScopeLabel = ShareLinkScope::label($scopeNorm, $linkDomain);
            $absoluteUrl = AppUrl::path($newShareUrl);
            if ($newShareLinkId > 0) {
                ShareLinkSessionStore::remember($newShareLinkId, $absoluteUrl);
            }
            $flash = 'Lien créé. Partagez-le par e-mail ou Bluesky ci-dessous (URL mémorisée 24 h dans cette session).';
        }
    } elseif ($action === 'send_share_email') {
        $linkId = (int) ($_POST['link_id'] ?? 0);
        $recipient = trim((string) ($_POST['recipient_email'] ?? ''));
        $personalMessage = trim((string) ($_POST['personal_message'] ?? ''));
        $shareUrl = ShareLinkSessionStore::get($linkId);
        $link = $repo->findByIdForUser($linkId, $userId);

        if ($shareUrl === null || $link === null) {
            $shareEmailError = 'URL du lien indisponible. Créez un nouveau lien pour partager.';
        } elseif ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $shareEmailError = 'Adresse e-mail du destinataire invalide.';
        } else {
            $scopeLabel = ShareLinkScope::label(
                ShareLinkScope::normalize((string) ($link['scope'] ?? '')),
                ShareLinkRepository::mediaDomainFromRow($link)
            );
            $sender = UserProfile::displayName(Auth::currentUser() ?? []);
            $sent = ShareLinkShare::sendByEmail($recipient, $sender, $shareUrl, $scopeLabel, $personalMessage);
            if ($sent) {
                $shareEmailOk = 'E-mail envoyé à ' . $recipient . '.';
            } else {
                $shareEmailError = 'Envoi impossible (vérifiez la configuration mail du serveur). Utilisez « Ouvrir dans ma messagerie ».';
            }
        }
    } elseif ($action === 'revoke') {
        $linkId = (int) ($_POST['link_id'] ?? 0);
        if ($service->revoke($linkId, $userId)) {
            $flash = 'Lien révoqué.';
        } else {
            $flashError = 'Impossible de révoquer ce lien.';
        }
    }
}

$links = $repo->listForUser($userId);
$shareUrlByLinkId = ShareLinkSessionStore::allForUserLinks($links);

$defaultScope = ShareLinkScope::normalize((string) ($_GET['scope'] ?? ShareLinkScope::COLLECTION));
$defaultDomain = MediaDomain::normalize((string) ($_GET['domain'] ?? MediaDomain::FILM));

$newShareAbsoluteUrl = $newShareUrl !== '' ? AppUrl::path($newShareUrl) : '';

View::render('gerer-partages', [
    'pageTitle' => 'Liens de partage',
    'links' => $links,
    'flash' => $flash,
    'flashError' => $flashError,
    'newShareUrl' => $newShareUrl,
    'newShareAbsoluteUrl' => $newShareAbsoluteUrl,
    'newShareLinkId' => $newShareLinkId,
    'newShareScopeLabel' => $newShareScopeLabel,
    'shareUrlByLinkId' => $shareUrlByLinkId,
    'shareEmailOk' => $shareEmailOk,
    'shareEmailError' => $shareEmailError,
    'foyerId' => $foyerId,
    'defaultScope' => $defaultScope,
    'defaultDomain' => $defaultDomain,
]);
