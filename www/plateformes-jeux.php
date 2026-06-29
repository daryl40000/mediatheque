<?php
/**
 * Administration des plateformes jeux (liste configurable).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GamePlatformAdmin;
use Moncine\GamePlatformRegistry;
use Moncine\MediaDomainGuards;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();
MediaDomainGuards::ensureGameContext('/plateformes-jeux.php');

$admin = new GamePlatformAdmin();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/plateformes-jeux.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = $admin->create(
            (string) ($_POST['platform_key'] ?? ''),
            (string) ($_POST['label'] ?? ''),
            (string) ($_POST['short_label'] ?? ''),
            (string) ($_POST['kind'] ?? 'other'),
            (string) ($_POST['console_store'] ?? ''),
            (int) ($_POST['sort_order'] ?? 100)
        );
        if ($result === true) {
            $message = 'Plateforme ajoutée.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'update') {
        $result = $admin->update(
            (string) ($_POST['platform_key'] ?? ''),
            (string) ($_POST['label'] ?? ''),
            (string) ($_POST['short_label'] ?? ''),
            (string) ($_POST['kind'] ?? 'other'),
            (string) ($_POST['console_store'] ?? ''),
            (int) ($_POST['sort_order'] ?? 100),
            !empty($_POST['active'])
        );
        if ($result === true) {
            $message = 'Plateforme mise à jour.';
        } else {
            $error = (string) $result;
        }
    }
}

$platforms = GamePlatformRegistry::listForAdmin();
$usageCounts = [];
foreach ($platforms as $row) {
    $key = (string) ($row['platform_key'] ?? '');
    if ($key !== '') {
        $usageCounts[$key] = $admin->countUsages($key);
    }
}

View::render('plateformes-jeux', [
    'pageTitle' => 'Plateformes jeux',
    'pageMediaDomain' => \Moncine\MediaDomain::JEU,
    'message' => $message,
    'error' => $error,
    'platforms' => $platforms,
    'usageCounts' => $usageCounts,
    'kindChoices' => GamePlatformAdmin::kindChoices(),
    'consoleStoreChoices' => GamePlatformAdmin::consoleStoreChoices(),
]);
