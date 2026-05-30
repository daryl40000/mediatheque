<?php
/**
 * Bootstrap minimal pour les commandes CLI (sans session web).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Moncine\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

Moncine\Requirements::abortIfNeeded();
