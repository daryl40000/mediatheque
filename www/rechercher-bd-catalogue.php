<?php
/**
 * API JSON — autocomplétion catalogue BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\BdRowMapper;
use Moncine\View;
use Moncine\UserContext;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!BdRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();
$albums = $repo->searchCatalog($query, 25);

$results = [];
foreach ($albums as $album) {
    $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
    $bibId = $repo->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
    $results[] = [
        'oeuvre_id' => $oeuvreId,
        'titre' => (string) ($album['display_titre'] ?? BdRowMapper::displayTitle($album)),
        'display_label' => (string) ($album['display_label'] ?? ''),
        'annee' => (int) ($album['annee'] ?? 0),
        'kind' => (string) ($album['kind'] ?? ''),
        'kind_label' => (string) ($album['kind_label'] ?? ''),
        'series_titre' => (string) ($album['series_titre'] ?? ''),
        'tome_summary' => (string) ($album['tome_summary'] ?? ''),
        'scenariste' => (string) ($album['scenariste'] ?? ''),
        'dessinateur' => (string) ($album['dessinateur'] ?? ''),
        'editeur' => (string) ($album['editeur'] ?? ''),
        'genre' => (string) ($album['genre'] ?? ''),
        'in_library' => $bibId !== null && $bibId > 0,
        'library_bib_id' => $bibId ?? 0,
        'library_url' => $bibId !== null && $bibId > 0 ? View::bdUrl($bibId) : '',
        'source' => 'bd_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
