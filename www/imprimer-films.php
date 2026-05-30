<?php
/**
 * Version imprimable de Mes films (mêmes filtres et tri que /films.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\FilmRepository;
use Moncine\PrintListService;
use Moncine\View;

if (!(new FilmRepository())->usesCatalogModel()) {
    header('Location: /films.php');
    exit;
}

View::render('imprimer-films', (new PrintListService())->viewDataForCollectionPrint($_GET));
