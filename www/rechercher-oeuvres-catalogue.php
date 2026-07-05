<?php
/**
 * API JSON — autocomplétion fiches catalogue par type de média (magazine, BD, etc.).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$domain = MediaDomain::normalize((string) ($_GET['domain'] ?? MediaDomain::FILM));
MediaContext::set($domain);

$query = trim((string) ($_GET['q'] ?? ''));
$rows = (new OeuvreRepository())->searchByTitrePrefix($query, 25);

$results = [];
foreach ($rows as $row) {
    $oeuvreId = (int) ($row['id'] ?? 0);
    if ($oeuvreId <= 0) {
        continue;
    }

    $titre = (string) ($row['titre'] ?? '');
    $annee = (int) ($row['annee'] ?? 0);
    $realisateur = trim((string) ($row['realisateur'] ?? ''));
    $label = $titre;
    if ($realisateur !== '') {
        $label .= ' — ' . $realisateur;
    }
    if ($annee > 0) {
        $label .= ' (' . $annee . ')';
    }

    $results[] = [
        'oeuvre_id' => $oeuvreId,
        'titre' => $titre,
        'display_label' => $label,
        'annee' => $annee,
        'source' => 'oeuvre_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
