<?php
/**
 * Version imprimable de Mes BD (liste des séries, mêmes filtres que /bd.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPrintListService;
use Moncine\BdRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureBdContext('/imprimer-bd.php');

if (!BdRepository::isAvailable()) {
    header('Location: /bd.php');
    exit;
}

View::render('imprimer-bd', (new BdPrintListService())->viewDataForCollectionPrint($_GET));
