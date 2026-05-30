<?php
/**
 * Groupe famille : création, invitations, quitter le groupe.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
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
$groupService = new FamilyGroupService();
$friendRepo = new FriendshipRepository();
$notifications = new NotificationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && FamilyGroupService::isAvailable()) {
    Csrf::rejectUnlessValid($_POST, '/mes-groupes.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = $groupService->createGroup($userId, (string) ($_POST['nom'] ?? ''));
        if (is_int($result)) {
            $success = 'Groupe famille créé.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'invite') {
        $foyerId = (int) ($_POST['foyer_id'] ?? 0);
        $inviteeId = (int) ($_POST['invitee_id'] ?? 0);
        $result = $groupService->inviteFriend($foyerId, $userId, $inviteeId);
        if (is_int($result)) {
            $success = 'Invitation envoyée.';
            $group = $groupService->findGroupForUser($userId);
            $groupName = (string) ($group['nom'] ?? '');
            $notifications->notifyGroupInvitation($inviteeId, $userId, $groupName, $result);
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'accept_invite') {
        $result = $groupService->acceptInvitation((int) ($_POST['invitation_id'] ?? 0), $userId);
        if ($result === true) {
            $success = 'Vous avez rejoint le groupe.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'decline_invite') {
        $result = $groupService->declineInvitation((int) ($_POST['invitation_id'] ?? 0), $userId);
        if ($result === true) {
            $success = 'Invitation refusée.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'leave') {
        $result = $groupService->leaveGroup($userId);
        if ($result === true) {
            $success = 'Vous avez quitté le groupe.';
        } else {
            $error = (string) $result;
        }
    }
}

$group = $groupService->findGroupForUser($userId);
$members = [];
$friends = [];
$pendingInvites = [];

if ($group !== null) {
    $foyerId = (int) ($group['id'] ?? 0);
    $members = $groupService->listMembers($foyerId);
}
if (FriendshipRepository::isAvailable()) {
    $friends = $friendRepo->listFriends($userId);
}
$pendingInvites = $groupService->listPendingInvitationsForUser($userId);

View::render('mes_groupes', [
    'pageTitle' => 'Mon groupe famille',
    'group' => $group,
    'members' => $members,
    'friends' => $friends,
    'pendingInvites' => $pendingInvites,
    'hasGroup' => $groupService->userHasFamilyGroup($userId),
    'socialAvailable' => FamilyGroupService::isAvailable(),
    'error' => $error,
    'success' => $success,
]);
