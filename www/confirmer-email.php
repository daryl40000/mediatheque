<?php
/**
 * Confirmation d’un changement d’adresse e-mail (lien envoyé à la nouvelle adresse).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\EmailChangeService;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $queryToken = trim((string) $_GET['token']);
    if ($queryToken !== '') {
        $result = (new EmailChangeService())->confirm($queryToken);
        if (($result['outcome'] ?? '') === 'ready') {
            if (Auth::isLoggedIn()) {
                Auth::logout();
            }
            header('Location: /connexion.php?email_changed=1');
            exit;
        }
        View::render('confirmer-email', [
            'pageTitle' => 'Confirmation e-mail',
            'outcome' => (string) ($result['outcome'] ?? 'error'),
            'message' => (string) ($result['message'] ?? ''),
            'layout' => false,
        ]);
        exit;
    }
}

header('Location: /connexion.php');
exit;
