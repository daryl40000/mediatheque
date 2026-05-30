<?php
/**
 * Déconnexion (POST + jeton CSRF pour éviter une déconnexion forcée par lien).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/');
Auth::logout();
header('Location: /connexion.php');
exit;
