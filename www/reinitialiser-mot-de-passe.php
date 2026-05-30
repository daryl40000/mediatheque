<?php
/**
 * Nouveau mot de passe via jeton reçu par e-mail.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\PasswordResetRepository;
use Moncine\View;

if (Auth::isLoggedIn()) {
    header('Location: /parametres.php');
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$resetRepo = new PasswordResetRepository();
$resetRepo->purgeExpired();

$error = '';
$success = false;
$tokenValid = $token !== '' && $resetRepo->findUserIdByToken($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/reinitialiser-mot-de-passe.php');
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
        $tokenValid = $resetRepo->findUserIdByToken($token) !== null;
    } else {
        $result = $resetRepo->resetPasswordWithToken($token, $password);
        if ($result === true) {
            $success = true;
            $tokenValid = false;
        } else {
            $error = (string) $result;
            $tokenValid = $resetRepo->findUserIdByToken($token) !== null;
        }
    }
}

View::render('reinitialiser-mot-de-passe', [
    'pageTitle' => 'Nouveau mot de passe',
    'token' => $token,
    'tokenValid' => $tokenValid,
    'error' => $error,
    'success' => $success,
    'layout' => false,
]);
