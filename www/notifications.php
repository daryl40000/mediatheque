<?php
/**
 * Liste des notifications de l’utilisateur connecté.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\NotificationRepository;
use Moncine\NotificationService;
use Moncine\UserContext;
use Moncine\View;

Auth::enforceWebAccess();

$userId = UserContext::currentUserId();
$service = new NotificationService();

$markId = max(0, (int) ($_GET['read'] ?? 0));
if ($markId > 0 && NotificationService::isAvailable()) {
    $row = (new NotificationRepository())->findByIdForUser($markId, $userId);
    $service->markRead($markId, $userId);
    $redirect = $row !== null
        ? View::notificationRedirectTarget($row)
        : '/notifications.php';
    header('Location: ' . $redirect);
    exit;
}

View::render('notifications', [
    'pageTitle' => 'Notifications',
    'notifications' => $service->listForUser($userId),
    'unreadCount' => $service->countUnread($userId),
    'allMarked' => isset($_GET['all_read']) && (string) $_GET['all_read'] === '1',
]);
