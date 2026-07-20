<?php
/**
 * Gestion des comptes (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\Exception\NotFoundException;
use Moncine\Exception\ValidationException;
use Moncine\FoyerRepository;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;
use Moncine\View;

Auth::denyUnlessAdmin('/');

$repo = new UtilisateurRepository();
$foyerRepo = new FoyerRepository();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/utilisateurs.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'set_registration_mode' && RegistrationService::isAvailable()) {
        (new RegistrationSettings())->setMode((string) ($_POST['registration_mode'] ?? ''));
        $success = 'Réglage d’inscription enregistré.';
    } elseif ($action === 'create') {
        // Phase E : create() lance une exception au lieu de renvoyer un message texte.
        try {
            $repo->create(
                (string) ($_POST['nom'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['role'] ?? UserRole::USER),
                0,
                (string) ($_POST['prenom'] ?? ''),
                (string) ($_POST['pseudo'] ?? '')
            );
            $success = 'Compte créé. L’utilisateur pourra rejoindre un groupe famille via Mes groupes.';
        } catch (ValidationException | NotFoundException $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'toggle') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $active = ((string) ($_POST['actif'] ?? '')) === '1';
        if ($userId === Auth::currentUserId()) {
            $error = 'Vous ne pouvez pas désactiver votre propre compte.';
        } else {
            $allowed = $repo->canSetActive($userId, $active);
            if ($allowed !== true) {
                $error = (string) $allowed;
            } elseif ($repo->setActive($userId, $active)) {
                $success = $active ? 'Compte réactivé.' : 'Compte désactivé.';
            }
        }
    } elseif ($action === 'reset_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $target = $repo->findById($userId);
        if ($target === null) {
            $error = 'Compte introuvable.';
        } else {
            $result = $repo->adminSetTemporaryPassword($userId);
            if (is_array($result)) {
                $success = 'Mot de passe provisoire pour « '
                    . (string) ($target['nom'] ?? '')
                    . ' » : '
                    . $result['password']
                    . ' — communiquez-le une seule fois.';
            } else {
                $error = (string) $result;
            }
        }
    } elseif ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId === Auth::currentUserId()) {
            $error = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $libraryCount = $repo->countLibraryEntries($userId);
            $result = $repo->delete($userId);
            if ($result === true) {
                $success = 'Compte supprimé.';
                if ($libraryCount > 0) {
                    $success .= ' ' . $libraryCount . ' entrée(s) personnelle(s) retirée(s).';
                }
            } else {
                $error = (string) $result;
            }
        }
    }
}

$registrationSettings = RegistrationService::isAvailable() ? new RegistrationSettings() : null;
$registrationService = RegistrationService::isAvailable() ? new RegistrationService() : null;

View::render('utilisateurs', [
    'pageTitle' => 'Comptes utilisateurs',
    'users' => $repo->listAll(),
    'foyers' => $foyerRepo->listAll(),
    'error' => $error,
    'success' => $success,
    'currentUserId' => Auth::currentUserId(),
    'registrationSettings' => $registrationSettings,
    'pendingRegistrations' => $registrationService !== null ? $registrationService->countPendingAdmin() : 0,
]);
