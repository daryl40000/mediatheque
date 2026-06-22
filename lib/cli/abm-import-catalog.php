#!/usr/bin/env php
<?php
/**
 * Importe un export JSON magazines dans le catalogue partagé (sans bibliothèque).
 *
 * Usage :
 *   php lib/cli/abm-import-catalog.php --json=install_seed/abm-tilt.json --dry-run
 *   php lib/cli/abm-import-catalog.php --json=install_seed/abm-magazines.json --stats
 *   php lib/cli/abm-import-catalog.php --json=install_seed/abm-tilt.json --download-covers
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moncine\MagazineCatalogImporter;

function abmImportCliUsage(): void
{
    $self = basename(__FILE__);
    fwrite(STDERR, <<<TXT
Usage : php lib/cli/{$self} --json=FILE [options]

Options :
  --json=FILE          Fichier JSON (export abm-fetch-catalog.php)
  --magazine=NAME      Filtrer par titre de revue
  --dry-run            Simulation sans écriture en base
  --no-skip-existing   Réimporter même si le numéro existe déjà (échoue sur doublon)
  --download-covers    Télécharger logos et couvertures (HTTPS, par lots)
  --cover-batch-size=N Couvertures max par passage (défaut 20, max 40)
  --stats              Afficher le résumé
  --help               Cette aide

TXT);
}

/** @return array{json: string, dryRun: bool, skipExisting: bool, downloadCovers: bool, coverBatchSize: int, stats: bool, magazineTitles: list<string>} */
function abmImportCliParseArgs(array $argv): array
{
    $options = getopt('', [
        'json:',
        'magazine:',
        'dry-run',
        'no-skip-existing',
        'download-covers',
        'cover-batch-size:',
        'stats',
        'help',
    ]);

    if ($options === false || isset($options['help']) || !isset($options['json'])) {
        abmImportCliUsage();
        exit(isset($options['help']) ? 0 : 1);
    }

    $root = dirname(__DIR__, 2);
    $jsonPath = (string) $options['json'];
    if (!str_starts_with($jsonPath, '/')) {
        $jsonPath = $root . '/' . ltrim($jsonPath, '/');
    }

    $magazineTitles = [];
    if (isset($options['magazine'])) {
        $magazineTitles = is_array($options['magazine']) ? $options['magazine'] : [$options['magazine']];
    }

    return [
        'json' => $jsonPath,
        'dryRun' => array_key_exists('dry-run', $options),
        'skipExisting' => !array_key_exists('no-skip-existing', $options),
        'downloadCovers' => array_key_exists('download-covers', $options),
        'coverBatchSize' => MagazineCatalogImporter::normalizeCoverBatchSize(
            (int) ($options['cover-batch-size'] ?? MagazineCatalogImporter::DEFAULT_COVER_BATCH_SIZE)
        ),
        'stats' => array_key_exists('stats', $options),
        'magazineTitles' => array_values(array_filter(array_map('strval', $magazineTitles))),
    ];
}

function abmImportCliPrintResult(array $result): void
{
    $prefix = !empty($result['dry_run']) ? '[dry-run] ' : '';
    echo $prefix . 'Séries créées : ' . (int) ($result['series_created'] ?? 0) . "\n";
    echo $prefix . 'Séries réutilisées : ' . (int) ($result['series_reused'] ?? 0) . "\n";
    echo $prefix . 'Numéros créés : ' . (int) ($result['issues_created'] ?? 0) . "\n";
    echo $prefix . 'Numéros ignorés : ' . (int) ($result['issues_skipped'] ?? 0) . "\n";
    echo 'Logos séries en cache : ' . (int) ($result['series_logos_cached'] ?? 0) . "\n";
    echo 'Couvertures en cache : ' . (int) ($result['issue_covers_cached'] ?? 0) . "\n";
    echo 'Échecs couvertures : ' . (int) ($result['issue_covers_failed'] ?? 0) . "\n";
    echo 'Couvertures restantes : ' . (int) ($result['issue_covers_remaining'] ?? 0) . "\n";
    echo 'Taille du lot : ' . (int) ($result['cover_batch_size'] ?? MagazineCatalogImporter::DEFAULT_COVER_BATCH_SIZE) . "\n";
    if (!empty($result['cover_batch_reached'])) {
        echo "Limite du lot atteinte — relancez pour continuer.\n";
    }

    if (($result['errors'] ?? []) !== []) {
        echo "\nErreurs :\n";
        foreach ($result['errors'] as $error) {
            echo '  - ' . $error . "\n";
        }
    }
}

$args = abmImportCliParseArgs($argv);

$export = MagazineCatalogImporter::parseJsonFile($args['json']);
if ($export === null) {
    fwrite(STDERR, 'Fichier JSON introuvable ou invalide : ' . $args['json'] . "\n");
    exit(1);
}

@set_time_limit(0);

$result = (new MagazineCatalogImporter())->importFromExportArray($export, [
    'dry_run' => $args['dryRun'],
    'skip_existing' => $args['skipExisting'],
    'download_covers' => $args['downloadCovers'],
    'cover_batch_size' => $args['coverBatchSize'],
    'series_filter' => $args['magazineTitles'],
]);

if ($args['stats'] || $args['dryRun']) {
    abmImportCliPrintResult($result);
}

exit(($result['errors'] ?? []) === [] ? 0 : 1);
