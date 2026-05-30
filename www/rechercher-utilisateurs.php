<?php
/**
 * Recherche d’utilisateurs par pseudo et ville (comptes visibles uniquement).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\FriendshipRepository;
use Moncine\SocialRateLimit;
use Moncine\UtilisateurRepository;
use Moncine\View;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$pseudoQuery = trim((string) ($_GET['pseudo'] ?? ''));
$villeQuery = trim((string) ($_GET['ville'] ?? ''));
$searched = $pseudoQuery !== '' || $villeQuery !== '';

$success = '';
$error = '';
if ((string) ($_GET['ami'] ?? '') === 'envoye') {
    $success = 'Demande d’ami envoyée.';
} elseif ((string) ($_GET['ami'] ?? '') === 'accepte') {
    $success = 'Vous êtes maintenant amis.';
}
if ((string) ($_GET['ami_erreur'] ?? '') !== '') {
    $error = (string) $_GET['ami_erreur'];
}
if ((string) ($_GET['bloque'] ?? '') === '1') {
    $success = 'Utilisateur bloqué.';
}
if ((string) ($_GET['debloque'] ?? '') === '1') {
    $success = 'Blocage levé.';
}

$results = [];
$relations = [];
if ($searched && $error === '') {
    if (!SocialRateLimit::allowUserSearch($userId)) {
        $error = SocialRateLimit::userSearchLimitMessage();
    } else {
        SocialRateLimit::recordUserSearch($userId);
        $results = (new UtilisateurRepository())->searchDiscoverableUsers($pseudoQuery, $villeQuery, $userId);
    }
    if (FriendshipRepository::isAvailable()) {
        $friendRepo = new FriendshipRepository();
        foreach ($results as $row) {
            $oid = (int) ($row['id'] ?? 0);
            $relations[$oid] = $friendRepo->relationStatus($userId, $oid);
        }
    }
}

View::render('rechercher_utilisateurs', [
    'pageTitle' => 'Rechercher des utilisateurs',
    'pseudoQuery' => $pseudoQuery,
    'villeQuery' => $villeQuery,
    'searched' => $searched,
    'results' => $results,
    'relations' => $relations,
    'socialAvailable' => FriendshipRepository::isAvailable(),
    'error' => $error,
    'success' => $success,
]);
