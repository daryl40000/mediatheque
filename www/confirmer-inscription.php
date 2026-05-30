<?php
/**
 * Confirmation d’inscription par jeton reçu par e-mail.
 *
 * Le lien e-mail ouvre cette page une fois : le jeton est stocké en session puis retiré de l’URL.
 * La confirmation effective se fait en POST (évite les scanners de messagerie).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\RegistrationConfirmSession;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\View;

if (Auth::isLoggedIn()) {
    header('Location: /');
    exit;
}

$service = new RegistrationService();

if (!RegistrationService::isAvailable() || !$service->settings()->isPublicRegistrationEnabled()) {
    header('Location: /connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $queryToken = trim((string) $_GET['token']);
    if ($queryToken !== '' && RegistrationConfirmSession::storeFromQueryToken($queryToken)) {
        header('Location: /confirmer-inscription.php', true, 302);
        exit;
    }
}

$token = RegistrationConfirmSession::getPlainToken();
if ($token === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
}

$outcome = '';
$message = '';
$tokenValid = false;
$confirmed = false;

if ($token !== '') {
    $tokenValid = $service->isConfirmTokenValid($token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/confirmer-inscription.php');
    $token = RegistrationConfirmSession::getPlainToken();
    if ($token === '') {
        $token = trim((string) ($_POST['token'] ?? ''));
    }
    $tokenValid = $service->isConfirmTokenValid($token);

    if (!$tokenValid) {
        $outcome = 'error';
        $message = 'Lien invalide ou expiré. Vous pouvez refaire une demande d’inscription si besoin.';
        RegistrationConfirmSession::clear();
    } else {
        $result = $service->confirmEmail($token);
        $outcome = (string) ($result['outcome'] ?? 'error');
        $message = (string) ($result['message'] ?? '');
        $confirmed = $outcome === 'ready' || $outcome === 'pending_admin';
        RegistrationConfirmSession::clear();
        $tokenValid = false;
        $token = '';
    }
}

View::render('confirmer-inscription', [
    'pageTitle' => 'Confirmation d’inscription',
    'token' => $token,
    'tokenValid' => $tokenValid,
    'outcome' => $outcome,
    'message' => $message,
    'confirmed' => $confirmed,
    'layout' => false,
]);
