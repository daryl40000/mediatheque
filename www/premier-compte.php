<?php
/**
 * Création du premier compte administrateur (installation).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\UtilisateurRepository;
use Moncine\View;

if (!Auth::needsSetup()) {
    header('Location: /connexion.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/premier-compte.php');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
    $result = (new UtilisateurRepository())->createFirstAdmin(
        (string) ($_POST['nom'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );
    if (is_int($result)) {
        Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        header('Location: /?setup=1');
        exit;
    }
    $error = (string) $result;
    }
}

View::render('premier-compte', [
    'pageTitle' => 'Premier compte',
    'error' => $error,
    'layout' => false,
]);
