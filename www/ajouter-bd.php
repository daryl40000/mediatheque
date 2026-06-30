<?php
/**
 * Redirection — les albums s’ajoutent désormais via une série.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::ensureBdContext('/ajouter-serie-bd.php');

$seriesId = (int) ($_GET['series_id'] ?? 0);
if ($seriesId > 0) {
    $query = http_build_query(array_filter([
        'series_id' => (string) $seriesId,
        'statut' => (string) ($_GET['statut'] ?? ''),
        'oeuvre_id' => (string) ($_GET['oeuvre_id'] ?? ''),
    ]));
    header('Location: /ajouter-tome-bd.php?' . $query);
    exit;
}

header('Location: /ajouter-serie-bd.php');
exit;
