<?php
/**
 * Fichiers joints à un jeu (abandonware, patch, ISO…).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameAttachmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'game_attachment' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    public function listForBibliotheque(int $bibId): array
    {
        if (!self::isAvailable() || $bibId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ga.id, ga.label, ga.original_filename, ga.created_at, ga.stored_object_id,
                    so.mime, so.size_bytes, so.relative_path
             FROM game_attachment ga
             INNER JOIN stored_objects so ON so.id = ga.stored_object_id
             WHERE ga.bibliotheque_id = ?
             ORDER BY ga.created_at DESC, ga.id DESC'
        );
        $stmt->execute([$bibId]);

        return array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $foyerId): bool
    {
        if (!self::isAvailable() || $storedObjectId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM game_attachment ga
             INNER JOIN bibliotheque b ON b.id = ga.bibliotheque_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE ga.stored_object_id = ?
               AND o.media_domain = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([
            $storedObjectId,
            MediaDomain::JEU,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return true|string */
    public function attachUploadedFile(
        int $bibId,
        int $userId,
        int $foyerId,
        string $tmpPath,
        string $originalName,
        int $fileSize,
        string $label = ''
    ): bool|string {
        if (!self::isAvailable()) {
            return 'Pièces jointes jeux non disponibles (migration en cours).';
        }

        if ((new GameRepository())->findByBibId($bibId, $userId, $foyerId) === null) {
            return 'Jeu introuvable.';
        }

        if ($tmpPath === '' || !is_readable($tmpPath)) {
            return 'Fichier invalide.';
        }

        $maxBytes = UploadLimits::maxAttachmentBytes();
        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            return UploadLimits::attachmentTooLargeApplicationMessage();
        }

        $originalName = trim($originalName);
        if ($originalName === '') {
            return 'Nom de fichier manquant.';
        }

        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        $mime = is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';

        $relative = self::buildRelativePath($bibId, $originalName);
        if ($relative === false) {
            return 'Chemin de stockage invalide.';
        }

        $absolute = MediaStorage::absolutePath($relative);
        if ($absolute === '') {
            return 'Chemin de stockage invalide.';
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de créer le dossier médias.';
        }

        if (!@move_uploaded_file($tmpPath, $absolute)) {
            if (!@rename($tmpPath, $absolute) && !@copy($tmpPath, $absolute)) {
                return 'Impossible d’enregistrer le fichier sur le serveur.';
            }
        }

        $stored = (new StoredObjectRepository())->create($relative, $fileSize, $mime);
        if ($stored === null) {
            @unlink($absolute);

            return 'Impossible d’enregistrer les métadonnées du fichier.';
        }

        $this->db->prepare(
            'INSERT INTO game_attachment (bibliotheque_id, stored_object_id, label, original_filename)
             VALUES (?, ?, ?, ?)'
        )->execute([
            $bibId,
            (int) ($stored['id'] ?? 0),
            trim($label),
            $originalName,
        ]);

        return true;
    }

    public function deleteById(int $attachmentId, int $bibId, int $userId, int $foyerId): bool
    {
        if (!self::isAvailable() || $attachmentId <= 0 || $bibId <= 0) {
            return false;
        }

        if ((new GameRepository())->findByBibId($bibId, $userId, $foyerId) === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT ga.stored_object_id, so.relative_path
             FROM game_attachment ga
             INNER JOIN stored_objects so ON so.id = ga.stored_object_id
             WHERE ga.id = ? AND ga.bibliotheque_id = ?
             LIMIT 1'
        );
        $stmt->execute([$attachmentId, $bibId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
        $relativePath = (string) ($row['relative_path'] ?? '');

        $this->db->prepare('DELETE FROM game_attachment WHERE id = ? AND bibliotheque_id = ?')
            ->execute([$attachmentId, $bibId]);

        if ($storedObjectId > 0) {
            (new StoredObjectRepository())->deleteById($storedObjectId);
        }

        if ($relativePath !== '') {
            $absolute = MediaStorage::absolutePath($relativePath);
            if ($absolute !== '' && is_file($absolute)) {
                @unlink($absolute);
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function hydrateRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['stored_object_id'] = (int) ($row['stored_object_id'] ?? 0);
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['size_label'] = UploadLimits::formatBytesLabel((int) ($row['size_bytes'] ?? 0));
        $row['display_label'] = trim((string) ($row['label'] ?? '')) !== ''
            ? trim((string) $row['label'])
            : (string) ($row['original_filename'] ?? 'Fichier');

        return $row;
    }

    private static function buildRelativePath(int $bibId, string $originalName): string|false
    {
        if ($bibId <= 0) {
            return false;
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $slug = self::slugify($base, 'fichier');
        $suffix = $ext !== '' ? '.' . preg_replace('/[^a-z0-9]+/', '', $ext) : '';
        $unique = substr(sha1($originalName . microtime(true)), 0, 8);

        return MediaStorage::relativePath('game', (string) $bibId, $slug . '-' . $unique . $suffix);
    }

    private static function slugify(string $text, string $fallback): string
    {
        $text = trim($text);
        if ($text === '') {
            return $fallback;
        }

        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '');
        $text = trim($text, '-');

        return $text !== '' ? substr($text, 0, 80) : $fallback;
    }
}
