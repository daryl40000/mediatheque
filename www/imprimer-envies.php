<?php
/**
 * Version imprimable de Mes envies (mêmes filtres et tri que /souhaits.php).
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

View::render('imprimer-envies', (new PrintListService())->viewDataForWishlistPrint($_GET));
