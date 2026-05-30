<?php
/**
 * Envoyer une demande d’ami (depuis la recherche utilisateurs).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\FriendshipRepository;
use Moncine\NotificationService;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$redirect = '/rechercher-utilisateurs.php';
$pseudo = trim((string) ($_POST['return_pseudo'] ?? $_GET['pseudo'] ?? ''));
$ville = trim((string) ($_POST['return_ville'] ?? $_GET['ville'] ?? ''));
if ($pseudo !== '') {
    $redirect .= '?pseudo=' . rawurlencode($pseudo);
}
if ($ville !== '') {
    $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'ville=' . rawurlencode($ville);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !FriendshipRepository::isAvailable()) {
    header('Location: ' . $redirect);
    exit;
}

Csrf::rejectUnlessValid($_POST, $redirect);

$addresseeId = (int) ($_POST['addressee_id'] ?? 0);
$result = (new FriendshipRepository())->sendRequest($userId, $addresseeId);

if (is_int($result)) {
    $friendRepo = new FriendshipRepository();
    if ($friendRepo->areFriends($userId, $addresseeId)) {
        (new NotificationService())->notifyFriendAccepted($addresseeId, $userId);
        $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'ami=accepte';
    } else {
        (new NotificationService())->notifyFriendRequest($addresseeId, $userId);
        $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'ami=envoye';
    }
} elseif ($result === true) {
    $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'ami=accepte';
} else {
    $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'ami_erreur=' . rawurlencode((string) $result);
}

header('Location: ' . $redirect);
exit;
