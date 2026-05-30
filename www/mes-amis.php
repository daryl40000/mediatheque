<?php
/**
 * Mes amis et demandes d’amitié en attente.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\Database;
use Moncine\FamilyGroupService;
use Moncine\FriendshipRepository;
use Moncine\NotificationService;
use Moncine\View;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$error = '';
$success = '';
$friendRepo = new FriendshipRepository();
$notifications = new NotificationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && FriendshipRepository::isAvailable()) {
    Csrf::rejectUnlessValid($_POST, '/mes-amis.php');
    $action = (string) ($_POST['action'] ?? '');
    $friendshipId = (int) ($_POST['friendship_id'] ?? 0);

    if ($action === 'accept') {
        $stmt = Database::getInstance()->prepare(
            'SELECT requester_id FROM friendships WHERE id = ? AND addressee_id = ? LIMIT 1'
        );
        $stmt->execute([$friendshipId, $userId]);
        $requesterId = (int) $stmt->fetchColumn();

        $result = $friendRepo->acceptRequest($friendshipId, $userId);
        if ($result === true) {
            $success = 'Demande acceptée.';
            if ($requesterId > 0) {
                $notifications->notifyFriendAccepted($requesterId, $userId);
            }
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'reject') {
        $result = $friendRepo->rejectRequest($friendshipId, $userId);
        if ($result === true) {
            $success = 'Demande refusée.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'cancel') {
        $result = $friendRepo->cancelRequest($friendshipId, $userId);
        if ($result === true) {
            $success = 'Demande annulée.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'block') {
        $blockedId = (int) ($_POST['blocked_user_id'] ?? 0);
        $result = $friendRepo->blockUser($userId, $blockedId);
        if ($result === true) {
            $success = 'Utilisateur bloqué.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'unblock') {
        $blockedId = (int) ($_POST['blocked_user_id'] ?? 0);
        $result = $friendRepo->unblockUser($userId, $blockedId);
        if ($result === true) {
            $success = 'Blocage levé.';
        } else {
            $error = (string) $result;
        }
    }
}

$groupMembers = [];
$groupName = '';
$groupService = new FamilyGroupService();
if (FamilyGroupService::isAvailable()) {
    $group = $groupService->findGroupForUser($userId);
    if ($group !== null) {
        $groupName = (string) ($group['nom'] ?? '');
        $foyerId = (int) ($group['id'] ?? 0);
        foreach ($groupService->listMembers($foyerId) as $member) {
            if ((int) ($member['id'] ?? 0) !== $userId) {
                $groupMembers[] = $member;
            }
        }
    }
}

View::render('mes_amis', [
    'pageTitle' => 'Mes amis',
    'friends' => FriendshipRepository::isAvailable() ? $friendRepo->listFriends($userId) : [],
    'pendingReceived' => FriendshipRepository::isAvailable() ? $friendRepo->listPendingReceived($userId) : [],
    'pendingSent' => FriendshipRepository::isAvailable() ? $friendRepo->listPendingSent($userId) : [],
    'blockedUsers' => FriendshipRepository::isAvailable() ? $friendRepo->listBlockedUsers($userId) : [],
    'groupMembers' => $groupMembers,
    'groupName' => $groupName,
    'socialAvailable' => FriendshipRepository::isAvailable(),
    'error' => $error,
    'success' => $success,
]);
