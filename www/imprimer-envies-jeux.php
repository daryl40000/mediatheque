<?php
/**
 * Version imprimable de Mes envies jeux (mêmes filtres et tri que /jeux-envies.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GamePrintListService;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureGameContext('/imprimer-envies-jeux.php');

if (!(new GameRepository())->isAvailable()) {
    header('Location: /jeux-envies.php');
    exit;
}

View::render('imprimer-envies-jeux', (new GamePrintListService())->viewDataForWishlistPrint($_GET));
