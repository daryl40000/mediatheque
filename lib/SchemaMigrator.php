<?php
/**
 * Migrations SQL du paquet Moncine (install fraîche + upgrades).
 *
 * Les anciennes migrations My Webapp (002–015) sont dans sql/migrations_legacy/
 * et ne sont pas exécutées ici.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use PDOException;
use RuntimeException;

final class SchemaMigrator
{
    public const META_SCHEMA_VERSION = 'schema_version';
    public const META_PACKAGE_EDITION = 'package_edition';
    public const META_INSTALL_SEED_APPLIED = 'install_seed_applied';
    public const EDITION_YUNOHOST = 'yunohost';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Crée les tables de base depuis sql/schema.sql (install neuve). */
    public function applyBaseSchema(): void
    {
        $schemaFile = MONCINE_ROOT . '/sql/schema.sql';
        if (!is_file($schemaFile)) {
            throw new RuntimeException('Fichier sql/schema.sql introuvable.');
        }
        $sql = file_get_contents($schemaFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('sql/schema.sql vide ou illisible.');
        }
        $this->pdo->exec($sql);
    }

    /**
     * Applique les fichiers sql/migrations/*.sql non encore enregistrés.
     *
     * @return list<string> noms de fichiers appliqués
     */
    public function runPendingMigrations(): array
    {
        $this->ensureMigrationTables();

        $dir = MONCINE_ROOT . '/sql/migrations';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $applied = [];

        foreach ($files as $file) {
            $name = basename($file);
            if ($this->isMigrationApplied($name)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            $this->pdo->beginTransaction();
            try {
                foreach ($this->splitStatements($sql) as $statement) {
                    try {
                        $this->pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Migration relancée après échec partiel : colonne déjà ajoutée → on ignore.
                        $msg = $e->getMessage();
                        $ignorable = str_contains($msg, 'duplicate column')
                            || str_contains($msg, 'no such column');
                        if (!$ignorable) {
                            throw $e;
                        }
                    }
                }
                $this->pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
                $this->bumpSchemaVersionFromFilename($name);
                $this->pdo->commit();
                $applied[] = $name;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw new RuntimeException('Échec migration ' . $name . ' : ' . $e->getMessage(), 0, $e);
            }
        }

        $this->normalizeStoredEans();

        return $applied;
    }

    /**
     * Corrige les EAN déjà en base (espaces, tirets, etc.) — chiffres uniquement.
     */
    public function normalizeStoredEans(): void
    {
        $tables = [
            ['bibliotheque', 'ean'],
            ['oeuvre_eans', 'ean'],
            ['wishlist_targets', 'ean'],
        ];
        foreach ($tables as [$table, $column]) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $stmt = $this->pdo->query(
                'SELECT id, ' . $column . ' AS ean_value FROM ' . $table
                . ' WHERE ' . $column . " IS NOT NULL AND TRIM(" . $column . ") != ''"
            );
            if ($stmt === false) {
                continue;
            }
            $update = $this->pdo->prepare(
                'UPDATE ' . $table . ' SET ' . $column . ' = ? WHERE id = ?'
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $raw = (string) ($row['ean_value'] ?? '');
                $normalized = OeuvreEanRepository::normalizeEan($raw);
                if ($normalized !== $raw) {
                    $update->execute([$normalized, (int) ($row['id'] ?? 0)]);
                }
            }
        }
    }

    public function schemaVersion(): int
    {
        $this->ensureMigrationTables();
        $stmt = $this->pdo->prepare('SELECT value FROM app_metadata WHERE key = ?');
        $stmt->execute([self::META_SCHEMA_VERSION]);
        $value = $stmt->fetchColumn();

        return is_string($value) && $value !== '' ? (int) $value : 0;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?"
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    public function getMetadata(string $key): string
    {
        $this->ensureMigrationTables();
        $stmt = $this->pdo->prepare('SELECT value FROM app_metadata WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : '';
    }

    public function setMetadata(string $key, string $value): void
    {
        $this->ensureMigrationTables();
        $this->pdo->prepare(
            'INSERT INTO app_metadata (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        )->execute([$key, $value]);
    }

    private function ensureMigrationTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )'
        );
    }

    private function isMigrationApplied(string $name): bool
    {
        $check = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE name = ?');
        $check->execute([$name]);

        return (bool) $check->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        // Évite d’ignorer un CREATE TABLE précédé d’un commentaire -- en tête de bloc.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $statement = trim($part);
            if ($statement !== '' && !str_starts_with($statement, '--')) {
                $out[] = $statement;
            }
        }

        return $out;
    }

    private function bumpSchemaVersionFromFilename(string $filename): void
    {
        if (preg_match('/^(\d+)_/', $filename, $m) === 1) {
            $version = (int) $m[1];
            $current = $this->schemaVersion();
            if ($version > $current) {
                $this->setMetadata(self::META_SCHEMA_VERSION, (string) $version);
            }
        }
    }
}
