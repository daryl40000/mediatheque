<?php
/**
 * Inscription publique (si activée par l’administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\View;

if (Auth::needsSetup()) {
    header('Location: /premier-compte.php');
    exit;
}

if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

$service = new RegistrationService();
if (!RegistrationService::isAvailable() || !$service->settings()->isPublicRegistrationEnabled()) {
    header('Location: /connexion.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/inscription.php');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $result = $service->submitRequest(
            (string) ($_POST['nom'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            $password,
            (string) ($_POST['prenom'] ?? ''),
            (string) ($_POST['pseudo'] ?? '')
        );
        if ($result === true) {
            header('Location: /connexion.php?registered=1');
            exit;
        }
        $error = (string) $result;
    }
}

View::render('inscription', [
    'pageTitle' => 'Créer un compte',
    'error' => $error,
    'requiresApproval' => $service->settings()->requiresAdminApproval(),
    'layout' => false,
]);
