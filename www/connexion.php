<?php
/**
 * Connexion à Moncine.
 *
 * Page publique (bootstrap laisse passer). Après POST réussi, redirection vers ?redirect= ou /.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\SafeRedirect;
use Moncine\View;

if (Auth::needsSetup()) {
    header('Location: /premier-compte.php');
    exit;
}

if (Auth::isLoggedIn()) {
    $redirect = SafeRedirect::path((string) ($_GET['redirect'] ?? ''));
    if ($redirect !== '/') {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: /');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/connexion.php');
    $result = Auth::login(
        (string) ($_POST['login'] ?? $_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );
    if ($result === true) {
        header('Location: ' . SafeRedirect::path((string) ($_POST['redirect'] ?? '')));
        exit;
    }
    $error = is_string($result) ? $result : 'Connexion impossible.';
}

$registrationEnabled = RegistrationService::isAvailable()
    && (new RegistrationSettings())->isPublicRegistrationEnabled();

View::render('connexion', [
    'pageTitle' => 'Connexion',
    'error' => $error,
    'redirect' => trim((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '')),
    'registrationEnabled' => $registrationEnabled,
    'flashRegistered' => isset($_GET['registered']) && (string) $_GET['registered'] === '1',
    'flashConfirmed' => isset($_GET['confirmed']) && (string) $_GET['confirmed'] === '1',
    'flashPendingAdmin' => isset($_GET['pending_admin']) && (string) $_GET['pending_admin'] === '1',
    'flashAccountDeleted' => isset($_GET['account_deleted']) && (string) $_GET['account_deleted'] === '1',
    'flashEmailChanged' => isset($_GET['email_changed']) && (string) $_GET['email_changed'] === '1',
    'layout' => false,
]);
