<?php
/**
 * Point d'entrée commun : charge la config et prépare l'autoload simple.
 *
 * Chaque page www/*.php commence par : require_once …/lib/bootstrap.php
 * Ordre important : config → prérequis → session → base → protection des pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mbstring_polyfill.php';

// Autoload : charge lib/NomClasse.php quand on écrit Moncine\NomClasse
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

// Vérifie PDO SQLite et le dossier data avant toute requête
Moncine\Requirements::abortIfNeeded();

// Session web (questionnaire + connexion)
Moncine\QuizSession::start();

// Base SQLite + migrations (avant Auth : needsSetup() interroge la table utilisateurs).
Moncine\Database::getInstance();

// Domaine média actif (onglets Films / BD / …)
Moncine\MediaContext::bootstrap();

// Pages web uniquement : en-têtes de sécurité puis login / premier compte si besoin.
if (PHP_SAPI !== 'cli') {
    Moncine\SecurityHeaders::send();
    Moncine\Auth::enforceWebAccess();
}
