#!/usr/bin/env php
<?php
/**
 * Import ponctuel Joybase → tests du magazine Joystick (feuille Tests uniquement).
 *
 * Prérequis : la série Joystick et ses numéros sont déjà en catalogue.
 *
 * Usage :
 *   php lib/cli/joybase-import-joystick-tests.php --file=/chemin/Joybase.ods --dry-run
 *   php lib/cli/joybase-import-joystick-tests.php --file=/chemin/Joybase.ods --set-rating-periods
 *   php lib/cli/joybase-import-joystick-tests.php --file=/chemin/Joybase.ods --limit=20
 *
 * Script jetable / spécifique Joybase v1212 — ne pas généraliser.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moncine\JoybaseJoystickTestsImporter;

function joybaseCliUsage(): void
{
    $self = basename(__FILE__);
    fwrite(STDERR, <<<TXT
Usage : php lib/cli/{$self} --file=FILE [options]

Options :
  --file=FILE            ODS Joybase (feuille « Tests »)
  --series=NAME          Titre de la série (défaut : Joystick)
  --tag=TAG              Tag sujet si la série en a plusieurs (ex. PC)
  --set-rating-periods   Enregistre aussi en base les périodes Joybase
                         (1–139=/100, 140+=/10). Les notes de l’import
                         utilisent toujours ces barèmes, même sans cette option.
  --limit=N              N premières lignes utiles (tests)
  --dry-run              Simulation sans écriture
  --help                 Cette aide

TXT);
}

/** @return array{file: string, dryRun: bool, seriesTitle: string, tag: string, setRatingPeriods: bool, limit: int} */
function joybaseCliParseArgs(array $argv): array
{
    $options = getopt('', [
        'file:',
        'series:',
        'tag:',
        'set-rating-periods',
        'limit:',
        'dry-run',
        'help',
    ]);

    if ($options === false || isset($options['help']) || !isset($options['file'])) {
        joybaseCliUsage();
        exit(isset($options['help']) ? 0 : 1);
    }

    $file = (string) $options['file'];
    if (!str_starts_with($file, '/')) {
        $file = dirname(__DIR__, 2) . '/' . ltrim($file, '/');
    }

    return [
        'file' => $file,
        'dryRun' => array_key_exists('dry-run', $options),
        'seriesTitle' => trim((string) ($options['series'] ?? JoybaseJoystickTestsImporter::DEFAULT_SERIES_TITLE)),
        'tag' => trim((string) ($options['tag'] ?? '')),
        'setRatingPeriods' => array_key_exists('set-rating-periods', $options),
        'limit' => max(0, (int) ($options['limit'] ?? 0)),
    ];
}

$args = joybaseCliParseArgs($argv);

echo "Joybase → Joystick (tests)\n";
echo 'Fichier : ' . $args['file'] . "\n";
echo 'Série   : ' . $args['seriesTitle'] . "\n";
echo 'Mode    : ' . ($args['dryRun'] ? 'DRY-RUN (aucune écriture)' : 'IMPORT RÉEL') . "\n";
if ($args['limit'] > 0) {
    echo 'Limite  : ' . $args['limit'] . " lignes\n";
}
echo "\n";

$importer = new JoybaseJoystickTestsImporter([
    'dryRun' => $args['dryRun'],
    'seriesTitle' => $args['seriesTitle'],
    'tag' => $args['tag'],
    'setRatingPeriods' => $args['setRatingPeriods'],
    'limit' => $args['limit'],
]);

$result = $importer->importFromOds($args['file']);

echo 'Série trouvée : #' . $result['series_id'] . ' « ' . $result['series_titre'] . " »\n";
echo 'Lignes lues   : ' . $result['rows_read'] . "\n";
echo 'Jeux créés    : ' . $result['games_created'] . ' | réutilisés : ' . $result['games_reused'] . "\n";
echo 'Sujets créés  : ' . $result['subjects_created'] . ' | réutilisés : ' . $result['subjects_reused'] . "\n";
echo 'Liens nouveaux: ' . $result['linked'] . ' | mis à jour : ' . $result['links_updated'] . "\n";
echo 'Notes posées  : ' . $result['scores_set']
    . ' | sans note : ' . ($result['scores_absent'] ?? 0)
    . ' | illisibles : ' . $result['scores_skipped'] . "\n";
echo 'N° manquants  : ' . $result['issues_missing'] . "\n";

if ($result['warnings'] !== []) {
    echo "\nAvertissements (" . count($result['warnings']) . ") :\n";
    foreach (array_slice($result['warnings'], 0, 40) as $warning) {
        echo '  - ' . $warning . "\n";
    }
    if (count($result['warnings']) > 40) {
        echo '  … et ' . (count($result['warnings']) - 40) . " de plus\n";
    }
}

if ($result['errors'] !== []) {
    echo "\nErreurs (" . count($result['errors']) . ") :\n";
    foreach (array_slice($result['errors'], 0, 40) as $error) {
        echo '  - ' . $error . "\n";
    }
    if (count($result['errors']) > 40) {
        echo '  … et ' . (count($result['errors']) - 40) . " de plus\n";
    }
    exit(1);
}

echo "\nTerminé.\n";
exit(0);
