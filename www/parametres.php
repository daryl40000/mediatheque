<?php
/**
 * Paramètres du compte : identité et mot de passe.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\FamilyGroupService;
use Moncine\FoyerRepository;
use Moncine\UserProfile;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;
use Moncine\View;

$userId = Auth::currentUserId();
$repo = new UtilisateurRepository();
$user = $repo->findById($userId);

if ($user === null) {
    header('Location: /connexion.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/parametres.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $searchable = (string) ($_POST['searchable'] ?? '0') === '1';
        $result = $repo->updateProfile(
            $userId,
            (string) ($_POST['nom'] ?? ''),
            (string) ($_POST['prenom'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['pseudo'] ?? ''),
            (string) ($_POST['ville'] ?? ''),
            $searchable,
            (string) ($_POST['profile_password'] ?? ''),
            (string) ($_POST['steam_id'] ?? '')
        );
        if ($result === true) {
            $success = 'Profil mis à jour.';
            $user = $repo->findById($userId) ?? $user;
        } elseif (is_string($result) && str_contains($result, 'lien de confirmation')) {
            $success = $result;
            $user = $repo->findById($userId) ?? $user;
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if ($new !== $confirm) {
            $error = 'Les deux nouveaux mots de passe ne correspondent pas.';
        } else {
            $result = $repo->changePassword(
                $userId,
                (string) ($_POST['current_password'] ?? ''),
                $new
            );
            if ($result === true) {
                $success = 'Mot de passe modifié.';
            } else {
                $error = (string) $result;
            }
        }
    } elseif ($action === 'delete_account') {
        $result = $repo->deleteOwnAccount($userId, (string) ($_POST['current_password'] ?? ''));
        if ($result === true) {
            Auth::logout();
            header('Location: /connexion.php?account_deleted=1');
            exit;
        }
        $error = (string) $result;
    }
}

$foyer = (new FoyerRepository())->findForUser($userId);

$canDeleteAccount = !UserRole::isAdmin((string) ($user['role'] ?? ''));
$isSoloGroupMember = false;
if ($foyer !== null && $canDeleteAccount) {
    $foyerId = (int) ($foyer['id'] ?? 0);
    if ($foyerId > 0 && FamilyGroupService::isAvailable()) {
        $db = \Moncine\Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM group_members WHERE foyer_id = ?');
        $stmt->execute([$foyerId]);
        $isSoloGroupMember = (int) $stmt->fetchColumn() === 1;
    }
}

View::render('parametres', [
    'pageTitle' => 'Mon compte',
    'user' => $user,
    'displayName' => UserProfile::displayName($user),
    'foyer' => $foyer,
    'error' => $error,
    'success' => $success,
    'maxPseudoLength' => UserProfile::MAX_PSEUDO_LENGTH,
    'maxVilleLength' => UserProfile::MAX_VILLE_LENGTH,
    'isSearchable' => UserProfile::isSearchable($user),
    'canDeleteAccount' => $canDeleteAccount,
    'isSoloGroupMember' => $isSoloGroupMember,
    'steamModuleReady' => \Moncine\GameSchema::hasUserSteamIdColumn(),
]);
