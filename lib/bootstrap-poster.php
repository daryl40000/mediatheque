<?php
/**
 * Bootstrap minimal pour poster.php — pas de session, base ni contrôle de connexion.
 * Évite qu’une requête d’image renvoie du HTML (login) ou corrompe le fichier binaire.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mbstring_polyfill.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Moncine\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

Moncine\Requirements::abortIfNeeded();
