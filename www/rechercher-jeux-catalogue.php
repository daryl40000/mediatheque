<?php
/**
 * API JSON — autocomplétion catalogue jeux (pont sujets magazines).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameRepository;
use Moncine\View;
use Moncine\UserContext;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!GameRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();
$games = $repo->searchCatalog($query, 25);

$results = [];
foreach ($games as $game) {
    $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
    $bibId = $repo->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
    $results[] = [
        'oeuvre_id' => $oeuvreId,
        'titre' => (string) ($game['display_titre'] ?? GameRowMapper::displayTitle($game)),
        'display_label' => (string) ($game['display_label'] ?? ''),
        'annee' => (int) ($game['annee'] ?? 0),
        'platform' => (string) ($game['platform'] ?? ''),
        'platform_list' => $game['platform_list'] ?? \Moncine\GamePlatformList::catalogKeysFromRow($game),
        'platform_label' => (string) ($game['platform_label'] ?? ''),
        'platform_short' => (string) ($game['platform_short'] ?? ''),
        'studio' => (string) ($game['studio'] ?? ''),
        'editeur' => (string) ($game['editeur'] ?? ''),
        'synopsis' => (string) ($game['synopsis'] ?? ''),
        'genre' => (string) ($game['genre'] ?? ''),
        'is_digital' => !empty($game['is_digital']),
        'in_library' => $bibId !== null && $bibId > 0,
        'library_bib_id' => $bibId ?? 0,
        'library_url' => $bibId !== null && $bibId > 0 ? View::gameUrl($bibId) : '',
        'source' => 'game_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
