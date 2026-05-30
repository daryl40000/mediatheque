<?php
/**
 * Consultation des groupes famille (administrateur, lecture seule depuis la phase 6).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\FoyerRepository;
use Moncine\UtilisateurRepository;
use Moncine\View;

Auth::denyUnlessAdmin('/');

View::render('foyers', [
    'pageTitle' => 'Groupes famille',
    'foyers' => (new FoyerRepository())->listAll(),
    'users' => (new UtilisateurRepository())->listAll(),
    'readOnly' => true,
    'error' => '',
    'success' => '',
]);
