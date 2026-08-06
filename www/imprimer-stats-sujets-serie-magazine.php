<?php
/**
 * Version imprimable / PDF des sujets filtrés (catégorie + année) — stats série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazinePrintListService;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/imprimer-stats-sujets-serie-magazine.php');

if (!MagazineRepository::isAvailable()) {
    header('Location: /magazines.php');
    exit;
}

$data = (new MagazinePrintListService())->viewDataForSeriesStatsSubjectsPrint($_GET);
if ($data === null) {
    http_response_code(404);
    View::render('stats-serie-magazine', [
        'pageTitle' => 'Liste introuvable',
        'series' => null,
        'statut' => '',
        'stats' => null,
        'wideLayout' => true,
    ]);
    exit;
}

View::render('imprimer-stats-sujets-serie-magazine', $data);
