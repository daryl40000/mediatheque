<?php
/**
 * Bootstrap PHPUnit : base SQLite isolée dans un dossier temporaire.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

// Dossier data dédié aux tests (jamais data/ du projet).
$testDataDir = sys_get_temp_dir() . '/moncine-phpunit-' . (string) getmypid();
if (!is_dir($testDataDir)) {
    mkdir($testDataDir, 0750, true);
}
putenv('MONCINE_DATA_PATH=' . $testDataDir);
$_ENV['MONCINE_DATA_PATH'] = $testDataDir;

// Sessions de test dans le même dossier temporaire (évite /var/lib/php/sessions).
ini_set('session.save_path', $testDataDir);

require_once $projectRoot . '/lib/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Moncine\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = MONCINE_ROOT . '/lib/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$errors = Moncine\Requirements::check();
if ($errors !== []) {
    fwrite(STDERR, "Prérequis tests manquants :\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

require_once $projectRoot . '/vendor/autoload.php';
