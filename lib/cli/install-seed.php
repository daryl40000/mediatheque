#!/usr/bin/env php
<?php
/**
 * Applique la graine d’installation (CSV catalogue + ZIP affiches) si l’instance est vide.
 *
 * Usage : php lib/cli/install-seed.php
 * Appelé par scripts/install YunoHost après les migrations SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moncine\Database;
use Moncine\InstallSeed;
use Moncine\SchemaMigrator;

$pdo = Database::getInstance();
$result = (new InstallSeed(new SchemaMigrator($pdo)))->applyIfEligible();

echo $result['message'] . "\n";

if (($result['errors'] ?? []) !== []) {
    echo "Avertissements / erreurs :\n";
    foreach ($result['errors'] as $err) {
        echo "  - {$err}\n";
    }
}

if ($result['status'] === 'error') {
    exit(1);
}

exit(0);
