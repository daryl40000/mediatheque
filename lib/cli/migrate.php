#!/usr/bin/env php
<?php
/**
 * Vérifie / applique les migrations paquet (sql/migrations/).
 *
 * Usage :
 *   php lib/cli/migrate.php
 *   php lib/cli/migrate.php --fresh   (base absente uniquement)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Moncine\Database;
use Moncine\SchemaMigrator;

$fresh = in_array('--fresh', $argv ?? [], true);
$dbFile = MONCINE_DB_FILE;

if ($fresh && is_file($dbFile)) {
    fwrite(STDERR, "Base déjà présente : {$dbFile}\n");
    fwrite(STDERR, "Supprimez-la manuellement puis relancez --fresh.\n");
    exit(1);
}

$pdo = Database::getInstance();
$migrator = new SchemaMigrator($pdo);

echo 'Édition : ' . SchemaMigrator::EDITION_YUNOHOST . "\n";
echo 'Version schéma : ' . $migrator->schemaVersion() . "\n";
echo 'Base : ' . $dbFile . "\n";

$check = $pdo->query('SELECT name FROM schema_migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
if ($check === []) {
    echo "Aucune migration enregistrée (anormal après getInstance).\n";
    exit(1);
}

echo "Migrations enregistrées :\n";
foreach ($check as $name) {
    echo "  - {$name}\n";
}

exit(0);
