<?php
/**
 * Vérifie que PHP est correctement configuré avant d'utiliser la base.
 */

declare(strict_types=1);

namespace Moncine;

final class Requirements
{
    /** @return list<string> Messages d'erreur (vide = tout va bien). */
    public static function check(): array
    {
        $errors = [];

        if (!extension_loaded('pdo')) {
            $errors[] = 'L\'extension PHP PDO n\'est pas installée.';
        } elseif (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $errors[] = 'Le pilote PDO SQLite est manquant. Installez le paquet php-sqlite3 (ex. sudo apt install php8.3-sqlite3).';
        }

        if (!is_dir(MONCINE_DATA) && !@mkdir(MONCINE_DATA, 0750, true)) {
            $errors[] = 'Impossible de créer le dossier data/ : ' . MONCINE_DATA;
        } elseif (!is_writable(MONCINE_DATA)) {
            $errors[] = 'Le dossier data/ n\'est pas accessible en écriture : ' . MONCINE_DATA;
        }

        return $errors;
    }

    /** Affiche une page d'erreur lisible et arrête le script. */
    public static function abortIfNeeded(): void
    {
        $errors = self::check();
        if ($errors === []) {
            return;
        }

        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Moncine — configuration</title>';
        echo '<style>body{font-family:system-ui;max-width:560px;margin:2rem auto;padding:0 1rem;line-height:1.5}';
        echo 'code{background:#eee;padding:.15rem .35rem;border-radius:4px}</style></head><body>';
        echo '<h1>Configuration PHP incomplète</h1>';
        echo '<p>Moncine ne peut pas démarrer :</p><ul>';
        foreach ($errors as $err) {
            echo '<li>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul>';
        echo '<p><strong>Sur Ubuntu/Debian :</strong></p>';
        echo '<pre><code>sudo apt install php8.3-sqlite3</code></pre>';
        echo '<p>Puis relancez le serveur : <code>php -S localhost:8080 -t www</code></p>';
        echo '</body></html>';
        exit;
    }
}
