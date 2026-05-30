<?php
/**
 * Page statistiques de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::renderCollectionPageOrExit();

use Moncine\CollectionStats;
use Moncine\View;

$stats = (new CollectionStats())->getDashboard();

View::render('statistiques', [
    'pageTitle' => 'Statistiques',
    'stats' => $stats,
    'wideLayout' => true,
]);
