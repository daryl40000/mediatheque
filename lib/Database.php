<?php
/**
 * Connexion SQLite unique (singleton) et création des tables si besoin.
 *
 * « Singleton » = une seule connexion PDO réutilisée par toute l’application.
 * Au premier appel, on crée le fichier moncine.db (si besoin) et on applique les migrations.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect();
            self::migrate();
        }

        return self::$pdo;
    }

    /**
     * Réinitialise le singleton PDO (tests PHPUnit uniquement).
     *
     * @internal
     */
    public static function resetInstance(): void
    {
        self::$pdo = null;
    }

    private static function connect(): PDO
    {
        $dataDir = MONCINE_DATA;
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $dsn = 'sqlite:' . MONCINE_DB_FILE;
        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            FrenchSort::registerCollation($pdo);
            SearchMatch::registerSqlFunction($pdo);
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'could not find driver')
                ? 'Pilote SQLite manquant (installez php-sqlite3).'
                : 'Impossible d\'ouvrir la base. Vérifiez que data/ est accessible en écriture.';
            throw new PDOException($msg, 0, $e);
        }

        return $pdo;
    }

    /**
     * Met le schéma à jour automatiquement (install locale ou upgrade paquet YunoHost).
     */
    private static function migrate(): void
    {
        $migrator = new SchemaMigrator(self::$pdo);

        // Première installation : toutes les tables depuis sql/schema.sql.
        if (!$migrator->tableExists('oeuvres')) {
            $migrator->applyBaseSchema();
        }

        // Fichiers numérotés dans sql/migrations/ (001, 002, …) non encore appliqués.
        $migrator->runPendingMigrations();
        FoyerMigration::runIfNeeded(self::$pdo);
        SocialMigration::runIfNeeded(self::$pdo);
        $migrator->setMetadata(SchemaMigrator::META_PACKAGE_EDITION, SchemaMigrator::EDITION_YUNOHOST);

        // Correctif ponctuel si une ancienne base avait une mauvaise clé sur historique.
        HistoriqueSchema::repairForeignKeyIfNeeded(self::$pdo);
    }
}
