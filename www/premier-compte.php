<?php
/**
 * Création du premier compte administrateur (installation).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\Exception\NotFoundException;
use Moncine\Exception\RepositoryException;
use Moncine\Exception\ValidationException;
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
        try {
            (new UtilisateurRepository())->createFirstAdmin(
                (string) ($_POST['nom'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? '')
            );
            Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            header('Location: /?setup=1');
            exit;
        } catch (ValidationException | NotFoundException | RepositoryException $e) {
            $error = $e->getMessage();
        }
    }
}

View::render('premier-compte', [
    'pageTitle' => 'Premier compte',
    'error' => $error,
    'layout' => false,
]);
