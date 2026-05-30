<?php
/**
 * Sauvegarde et restauration complète de la base SQLite Moncine (admin uniquement).
 */

declare(strict_types=1);

namespace Moncine;

use SQLite3;

final class DatabaseBackupService
{
    /** Tables minimales attendues dans une sauvegarde Moncine valide. */
    private const REQUIRED_TABLES = [
        'app_metadata',
        'oeuvres',
        'utilisateurs',
        'bibliotheque',
    ];

    public const RESTORE_CONFIRM_PHRASE = 'RESTAURER';

    public static function sqlite3Available(): bool
    {
        return class_exists(SQLite3::class);
    }

    /**
     * Vérifie le mot de passe de l’administrateur connecté (anti vol de session).
     */
    public function verifyAdminPassword(int $adminUserId, string $password): bool
    {
        if ($adminUserId <= 0 || !Auth::isAdmin() || Auth::currentUserId() !== $adminUserId) {
            return false;
        }

        $user = Auth::currentUser();
        if ($user === null) {
            return false;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return false;
        }

        $authRow = (new UtilisateurRepository())->findByEmailForAuthentication($email);
        if ($authRow === null || (int) ($authRow['id'] ?? 0) !== $adminUserId) {
            return false;
        }

        return UtilisateurRepository::verifyPassword($authRow, $password);
    }

    /**
     * Copie cohérente de moncine.db vers un fichier temporaire (API backup SQLite).
     *
     * @return true|string
     */
    public function exportToPath(string $destPath): bool|string
    {
        if (!self::sqlite3Available()) {
            return 'L’extension PHP SQLite3 est requise pour créer une sauvegarde.';
        }

        $sourcePath = MONCINE_DB_FILE;
        if (!is_file($sourcePath)) {
            return 'Fichier de base introuvable.';
        }

        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de préparer le dossier de sauvegarde.';
        }

        if (is_file($destPath)) {
            @unlink($destPath);
        }

        try {
            $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $dest = new SQLite3($destPath);
            $source->backup($dest);
            $dest->close();
            $source->close();
        } catch (\Throwable $e) {
            if (is_file($destPath)) {
                @unlink($destPath);
            }

            return 'Échec de la copie de la base : ' . $e->getMessage();
        }

        if (!is_file($destPath) || filesize($destPath) < 100) {
            return 'La sauvegarde générée est vide ou invalide.';
        }

        return true;
    }

    /**
     * Envoie le fichier de sauvegarde au navigateur puis le supprime.
     */
    public function sendDownload(string $tempPath): void
    {
        $filename = 'moncine-sauvegarde-' . date('Ymd-His') . '.db';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tempPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex');

        $handle = fopen($tempPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de lire la sauvegarde.');
        }

        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
        }
        fclose($handle);
        @unlink($tempPath);
    }

    /**
     * @return true|string
     */
    public function validateBackupFile(string $path): bool|string
    {
        if (!is_file($path)) {
            return 'Fichier de sauvegarde introuvable.';
        }

        $size = filesize($path);
        if ($size === false || $size < 100) {
            return 'Fichier trop petit pour être une base Moncine valide.';
        }

        if ($size > MONCINE_DB_BACKUP_MAX_BYTES) {
            return 'Fichier trop volumineux (limite '
                . (int) floor(MONCINE_DB_BACKUP_MAX_BYTES / (1024 * 1024))
                . ' Mo).';
        }

        $header = @file_get_contents($path, false, null, 0, 16);
        if ($header === false || !str_starts_with($header, 'SQLite format 3')) {
            return 'Ce fichier n’est pas une base SQLite valide.';
        }

        if (!self::sqlite3Available()) {
            return 'L’extension PHP SQLite3 est requise pour valider la sauvegarde.';
        }

        try {
            $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
            foreach (self::REQUIRED_TABLES as $table) {
                $escaped = SQLite3::escapeString($table);
                $exists = (int) $db->querySingle(
                    "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = '{$escaped}' LIMIT 1"
                );
                if ($exists !== 1) {
                    $db->close();

                    return 'Sauvegarde incompatible : table « ' . $table . ' » absente.';
                }
            }

            if (!CatalogSchema::usesCatalogTables($this->openPdoFromPath($path))) {
                $db->close();

                return 'Cette base ne correspond pas au schéma catalogue Moncine actuel.';
            }

            $db->close();
        } catch (\Throwable) {
            return 'Impossible de lire la sauvegarde (fichier corrompu ou incompatible).';
        }

        return true;
    }

    /**
     * Remplace moncine.db par la sauvegarde validée. Conserve une copie de secours locale.
     *
     * @return true|string
     */
    public function restoreFromPath(string $uploadPath, int $adminUserId): bool|string
    {
        $validation = $this->validateBackupFile($uploadPath);
        if ($validation !== true) {
            if (is_file($uploadPath)) {
                @unlink($uploadPath);
            }

            return $validation;
        }

        $currentPath = MONCINE_DB_FILE;
        if (!is_file($currentPath)) {
            return 'Base actuelle introuvable.';
        }

        $snapshotDir = MONCINE_DATA . '/db_snapshots';
        if (!is_dir($snapshotDir) && !mkdir($snapshotDir, 0750, true) && !is_dir($snapshotDir)) {
            return 'Impossible de créer le dossier de secours.';
        }

        $preRestorePath = $snapshotDir . '/moncine-before-restore-' . date('Ymd-His') . '.db';
        if (!copy($currentPath, $preRestorePath)) {
            return 'Impossible de sauvegarder la base actuelle avant restauration.';
        }

        Database::resetInstance();
        $this->removeWalSidecars($currentPath);

        try {
            if (!copy($uploadPath, $currentPath)) {
                throw new \RuntimeException('copy failed');
            }
            @chmod($currentPath, 0640);
            $this->removeWalSidecars($currentPath);
            Database::resetInstance();
            Database::getInstance();
        } catch (\Throwable $e) {
            copy($preRestorePath, $currentPath);
            $this->removeWalSidecars($currentPath);
            Database::resetInstance();
            Database::getInstance();

            return 'Échec de la restauration : ' . $e->getMessage();
        } finally {
            if (is_file($uploadPath)) {
                @unlink($uploadPath);
            }
            $this->pruneOldSnapshots($snapshotDir, 5);
        }

        (new CatalogAuditLog())->log(
            $adminUserId,
            CatalogAuditLog::ACTION_DB_RESTORE,
            null,
            'Restauration complète depuis une sauvegarde .db'
        );

        return true;
    }

    public function logExport(int $adminUserId): void
    {
        (new CatalogAuditLog())->log(
            $adminUserId,
            CatalogAuditLog::ACTION_DB_EXPORT,
            null,
            'Export complet de la base SQLite'
        );
    }

    /** Chemin temporaire hors www pour export / import. */
    public function tempBackupPath(string $prefix): string
    {
        $dir = MONCINE_DATA . '/db_backups_tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir . '/' . $prefix . '-' . bin2hex(random_bytes(16)) . '.db';
    }

    private function openPdoFromPath(string $path): \PDO
    {
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    private function removeWalSidecars(string $dbPath): void
    {
        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $dbPath . $suffix;
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }
    }

    private function pruneOldSnapshots(string $dir, int $keep): void
    {
        $files = glob($dir . '/moncine-before-restore-*.db') ?: [];
        if (count($files) <= $keep) {
            return;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }
}
