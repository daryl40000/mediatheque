#!/usr/bin/env php
<?php
/**
 * Télécharge une fois le catalogue magazines ABM (métadonnées + URLs couvertures) en JSON.
 *
 * Outil ponctuel pour préparer un import catalogue — ne pas utiliser en production.
 * Supprimable après préparation des fichiers d’import.
 *
 * Usage :
 *   php lib/cli/abm-fetch-catalog.php
 *   php lib/cli/abm-fetch-catalog.php --output=install_seed/abm-magazines.json
 *   php lib/cli/abm-fetch-catalog.php --magazine=Tilt --stats
 *   php lib/cli/abm-fetch-catalog.php --magazine-id=29 --dry-run
 *   php lib/cli/abm-fetch-catalog.php --cache-dir=data/abm-fetch-cache
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moncine\AbmCatalogFetcher;

function abmCliUsage(): void
{
    $self = basename(__FILE__);
    fwrite(STDERR, <<<TXT
Usage : php lib/cli/{$self} [options]

Options :
  --output=FILE        Fichier JSON de sortie (défaut : install_seed/abm-magazines.json)
  --magazine=NAME      Filtrer par titre de revue (sous-chaîne, répétable)
  --magazine-id=ID     Filtrer par identifiant ABM (répétable)
  --cache-dir=DIR      Conserver les réponses brutes de l’API (réutilisation sans re-téléchargement)
  --dry-run            Télécharge et affiche les stats sans écrire le JSON
  --stats              Affiche un résumé après export
  --help               Affiche cette aide

Exemples :
  php lib/cli/{$self} --magazine=Tilt --stats
  php lib/cli/{$self} --cache-dir=data/abm-fetch-cache --output=install_seed/abm-tilt.json

TXT);
}

/** @return array{output: string, cacheDir: ?string, dryRun: bool, stats: bool, magazineTitles: list<string>, magazineIds: list<int>} */
function abmCliParseArgs(array $argv): array
{
    $options = getopt('', [
        'output:',
        'magazine:',
        'magazine-id:',
        'cache-dir:',
        'dry-run',
        'stats',
        'help',
    ]);

    if ($options === false || isset($options['help'])) {
        abmCliUsage();
        exit(isset($options['help']) ? 0 : 1);
    }

    $magazineTitles = [];
    if (isset($options['magazine'])) {
        $magazineTitles = is_array($options['magazine']) ? $options['magazine'] : [$options['magazine']];
    }

    $magazineIds = [];
    if (isset($options['magazine-id'])) {
        $rawIds = is_array($options['magazine-id']) ? $options['magazine-id'] : [$options['magazine-id']];
        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $magazineIds[] = $id;
            }
        }
    }

    $root = dirname(__DIR__, 2);

    return [
        'output' => (string) ($options['output'] ?? $root . '/install_seed/abm-magazines.json'),
        'cacheDir' => isset($options['cache-dir']) ? (string) $options['cache-dir'] : null,
        'dryRun' => array_key_exists('dry-run', $options),
        'stats' => array_key_exists('stats', $options),
        'magazineTitles' => array_values(array_filter(array_map('strval', $magazineTitles))),
        'magazineIds' => $magazineIds,
    ];
}

function abmCliPrintStats(array $export): void
{
    $stats = $export['stats'] ?? [];
    echo "Source : " . ($export['source'] ?? '') . "\n";
    echo "Généré : " . ($export['generated_at'] ?? '') . "\n";
    echo "Revues : " . (int) ($stats['series_count'] ?? 0) . "\n";
    echo "Numéros : " . (int) ($stats['issue_count'] ?? 0) . "\n";
    echo "Couvertures (URL) : " . (int) ($stats['issues_with_cover_url'] ?? 0) . "\n";

    if (($stats['series_count'] ?? 0) > 0 && ($stats['series_count'] ?? 0) <= 20) {
        echo "\nRevues exportées :\n";
        foreach ($export['series'] as $serie) {
            $issueCount = is_countable($serie['issues'] ?? null) ? count($serie['issues']) : 0;
            echo '  - ' . ($serie['titre'] ?? '?')
                . ' (ABM #' . (int) ($serie['abm_magazine_id'] ?? 0) . ', '
                . $issueCount . " numéro(s))\n";
        }
    }
}

$args = abmCliParseArgs($argv);

try {
    $fetcher = new AbmCatalogFetcher(
        $args['cacheDir'],
        $args['magazineTitles'],
        $args['magazineIds']
    );

    echo "Téléchargement ABM (choixapi=12 revues, choixapi=10 numéros)…\n";
    $export = $fetcher->fetchExport();

    if ($args['dryRun']) {
        echo "[dry-run] Aucun fichier écrit.\n";
        abmCliPrintStats($export);
        exit(0);
    }

    $output = $args['output'];
    $dir = dirname($output);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de créer le dossier : ' . $dir);
    }

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Échec de l’encodage JSON.');
    }

    if (file_put_contents($output, $json . "\n") === false) {
        throw new RuntimeException('Impossible d’écrire : ' . $output);
    }

    echo "Export écrit : {$output}\n";

    if ($args['stats']) {
        echo "\n";
        abmCliPrintStats($export);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
