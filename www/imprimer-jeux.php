<?php
/**
 * Version imprimable de Mes jeux (mêmes filtres et tri que /jeux.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GamePrintListService;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureGameContext('/imprimer-jeux.php');

if (!(new GameRepository())->isAvailable()) {
    header('Location: /jeux.php');
    exit;
}

View::render('imprimer-jeux', (new GamePrintListService())->viewDataForCollectionPrint($_GET));
