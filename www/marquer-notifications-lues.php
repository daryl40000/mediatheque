<?php
/**
 * Marque une ou toutes les notifications comme lues.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\NotificationService;
use Moncine\UserContext;

Auth::enforceWebAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /notifications.php');
    exit;
}

$backUrl = '/notifications.php';
Csrf::rejectUnlessValid($_POST, $backUrl);

$userId = UserContext::currentUserId();
$service = new NotificationService();

$action = (string) ($_POST['action'] ?? '');
if ($action === 'all') {
    $service->markAllRead($userId);
    header('Location: ' . $backUrl . '?all_read=1');
    exit;
}

$notificationId = max(0, (int) ($_POST['notification_id'] ?? 0));
if ($notificationId > 0) {
    $service->markRead($notificationId, $userId);
}

header('Location: ' . $backUrl);
exit;
