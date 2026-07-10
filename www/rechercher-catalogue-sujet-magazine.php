<?php
/**
 * API JSON — autocomplétion catalogue pour sujets magazine (jeu, film…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BibliothequeRepository;
use Moncine\GameRepository;
use Moncine\GameRowMapper;
use Moncine\MagazineSubjectCatalogLink;
use Moncine\MediaDomain;
use Moncine\UserContext;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$query = trim((string) ($_GET['q'] ?? ''));
$domain = MediaDomain::normalize((string) ($_GET['domain'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

if ($query === '' || !MagazineSubjectCatalogLink::isLinkableDomain($domain)) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$link = new MagazineSubjectCatalogLink();
$rows = $link->searchCatalog($domain, $query, 25);
$bibRepo = new BibliothequeRepository();
$results = [];

foreach ($rows as $row) {
    $oeuvreId = (int) ($row['oeuvre_id'] ?? $row['id'] ?? 0);
    if ($oeuvreId <= 0) {
        continue;
    }

    if ($domain === MediaDomain::JEU) {
        $bibId = (new GameRepository())->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
        $results[] = [
            'oeuvre_id' => $oeuvreId,
            'titre' => (string) ($row['display_titre'] ?? GameRowMapper::displayTitle($row)),
            'display_label' => (string) ($row['display_label'] ?? ''),
            'annee' => (int) ($row['annee'] ?? 0),
            'platform_short' => (string) ($row['platform_short'] ?? ''),
            'platform_label' => (string) ($row['platform_label'] ?? ''),
            'media_domain' => MediaDomain::JEU,
            'media_domain_label' => 'Jeu vidéo',
            'in_library' => $bibId !== null && $bibId > 0,
            'source' => 'media_catalog',
        ];
        continue;
    }

    $library = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId);
    $bibId = (int) ($library['id'] ?? 0);
    $titre = (string) ($row['titre'] ?? '');
    $annee = (int) ($row['annee'] ?? 0);
    $realisateur = trim((string) ($row['realisateur'] ?? ''));
    $displayLabel = $titre;
    if ($annee > 0) {
        $displayLabel .= ' (' . $annee . ')';
    }

    $results[] = [
        'oeuvre_id' => $oeuvreId,
        'titre' => $titre,
        'display_label' => $displayLabel,
        'annee' => $annee,
        'realisateur' => $realisateur,
        'media_domain' => MediaDomain::FILM,
        'media_domain_label' => 'Film',
        'in_library' => $bibId > 0,
        'source' => 'media_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
