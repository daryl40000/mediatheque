<?php
/**
 * Version imprimable / PDF d’une série magazine (liste des numéros et possession).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazinePrintListService;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

if (!MagazineRepository::isAvailable()) {
    header('Location: /magazines.php');
    exit;
}

$data = (new MagazinePrintListService())->viewDataForSeriesPrint($_GET);
if ($data === null) {
    http_response_code(404);
    View::render('serie-magazine', [
        'pageTitle' => 'Série introuvable',
        'series' => null,
        'issues' => [],
        'statut' => '',
        'searchQuery' => '',
        'hasSearch' => false,
        'totalAllIssues' => 0,
        'filteredCount' => 0,
        'pdfTextSearchEnabled' => false,
    ]);
    exit;
}

View::render('imprimer-serie-magazine', $data);
