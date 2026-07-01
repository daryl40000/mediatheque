<?php
/**
 * Liste imprimable des tomes d’une série BD.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPrintListService;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureBdContext('/imprimer-serie-bd.php');

$data = (new BdPrintListService())->viewDataForSeriesPrint($_GET);
if ($data === null) {
    http_response_code(404);
    View::render('imprimer-serie-bd', [
        'layout' => 'print',
        'pageTitle' => 'Série introuvable',
        'backUrl' => '/bd.php',
        'series' => null,
        'rows' => [],
        'filterSummary' => '',
        'sortSummary' => '',
        'totalCount' => 0,
        'truncated' => false,
        'maxRows' => BdPrintListService::MAX_ROWS,
        'kindLabel' => '',
    ]);
    exit;
}

View::render('imprimer-serie-bd', $data);
