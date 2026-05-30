<?php
/**
 * Demande de réinitialisation du mot de passe par e-mail.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\UtilisateurRepository;
use Moncine\View;

if (Auth::isLoggedIn()) {
    header('Location: /parametres.php');
    exit;
}

$error = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/mot-de-passe-oublie.php');
    $email = (string) ($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Indiquez votre adresse e-mail.';
    } else {
        (new UtilisateurRepository())->requestPasswordResetEmail($email);
        $sent = true;
    }
}

View::render('mot-de-passe-oublie', [
    'pageTitle' => 'Mot de passe oublié',
    'error' => $error,
    'sent' => $sent,
    'layout' => false,
]);
