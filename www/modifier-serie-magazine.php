<?php
/**
 * Formulaire : modifier une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines.php');

$seriesId = (int) ($_GET['series_id'] ?? 0);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
if ($series === null) {
    header('Location: /magazines.php');
    exit;
}

View::render('modifier-serie-magazine', [
    'pageTitle' => 'Modifier — ' . (string) ($series['titre'] ?? ''),
    'publicationTypes' => PublicationType::choices(),
    'series' => $series,
    'error' => (string) ($_GET['error'] ?? ''),
    'saved' => isset($_GET['saved']),
]);
