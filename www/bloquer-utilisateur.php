<?php
/**
 * Bloquer un utilisateur (depuis la recherche ou la liste d’amis).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\FriendshipRepository;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$redirect = trim((string) ($_POST['return_to'] ?? '/mes-amis.php'));
if ($redirect === '' || !str_starts_with($redirect, '/') || str_contains($redirect, '//')) {
    $redirect = '/mes-amis.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !FriendshipRepository::isAvailable()) {
    header('Location: ' . $redirect);
    exit;
}

Csrf::rejectUnlessValid($_POST, $redirect);

$blockedId = (int) ($_POST['blocked_user_id'] ?? 0);
$result = (new FriendshipRepository())->blockUser($userId, $blockedId);

$separator = str_contains($redirect, '?') ? '&' : '?';
if ($result === true) {
    header('Location: ' . $redirect . $separator . 'bloque=1');
} else {
    header('Location: ' . $redirect . $separator . 'ami_erreur=' . rawurlencode((string) $result));
}
exit;
