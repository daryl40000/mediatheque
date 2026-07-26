<?php
/**
 * Fichiers joints à une fiche jeu catalogue (manuel, soluce, patch…).
 *
 * Stockés au niveau de l’œuvre (partagés) : seuls les admins/modérateurs
 * catalogue peuvent en ajouter ou en supprimer ; tout utilisateur connecté
 * peut les consulter / télécharger.
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
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'game_attachment' LIMIT 1"
        );
        if ($stmt === false || $stmt->fetchColumn() === false) {
            return false;
        }

        // Après la migration 067, la clé est oeuvre_id (plus bibliotheque_id).
        $cols = $db->query('PRAGMA table_info(game_attachment)');
        if ($cols === false) {
            return false;
        }
        foreach ($cols->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if ((string) ($col['name'] ?? '') === 'oeuvre_id') {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    public function listForOeuvre(int $oeuvreId): array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ga.id, ga.label, ga.original_filename, ga.created_at, ga.stored_object_id,
                    so.mime, so.size_bytes, so.relative_path
             FROM game_attachment ga
             INNER JOIN stored_objects so ON so.id = ga.stored_object_id
             WHERE ga.oeuvre_id = ?
             ORDER BY ga.created_at DESC, ga.id DESC'
        );
        $stmt->execute([$oeuvreId]);

        return ListOf::assocRows(array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
    }

    /**
     * Tout utilisateur connecté peut lire un fichier joint catalogue
     * (manuel / soluce partagés sur la fiche œuvre).
     */
    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $_foyerId = 0): bool
    {
        if (!self::isAvailable() || $storedObjectId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM game_attachment ga
             INNER JOIN oeuvres o ON o.id = ga.oeuvre_id
             WHERE ga.stored_object_id = ?
               AND o.media_domain = ?
             LIMIT 1'
        );
        $stmt->execute([$storedObjectId, MediaDomain::JEU]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return true|string */
    public function attachUploadedFile(
        int $oeuvreId,
        string $tmpPath,
        string $originalName,
        int $fileSize,
        string $label = ''
    ): bool|string {
        if (!self::isAvailable()) {
            return 'Pièces jointes jeux non disponibles (migration en cours).';
        }

        if ($oeuvreId <= 0 || !$this->oeuvreIsGame($oeuvreId)) {
            return 'Jeu catalogue introuvable.';
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

        $relative = self::buildRelativePath($oeuvreId, $originalName);
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
            'INSERT INTO game_attachment (oeuvre_id, stored_object_id, label, original_filename)
             VALUES (?, ?, ?, ?)'
        )->execute([
            $oeuvreId,
            (int) ($stored['id'] ?? 0),
            trim($label),
            $originalName,
        ]);

        return true;
    }

    public function deleteById(int $attachmentId, int $oeuvreId): bool
    {
        if (!self::isAvailable() || $attachmentId <= 0 || $oeuvreId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT ga.stored_object_id, so.relative_path
             FROM game_attachment ga
             INNER JOIN stored_objects so ON so.id = ga.stored_object_id
             WHERE ga.id = ? AND ga.oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$attachmentId, $oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
        $relativePath = (string) ($row['relative_path'] ?? '');

        $this->db->prepare('DELETE FROM game_attachment WHERE id = ? AND oeuvre_id = ?')
            ->execute([$attachmentId, $oeuvreId]);

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

    private function oeuvreIsGame(int $oeuvreId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM oeuvres WHERE id = ? AND media_domain = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::JEU]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function hydrateRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['stored_object_id'] = (int) ($row['stored_object_id'] ?? 0);
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['size_label'] = UploadLimits::formatBytesLabel($row['size_bytes']);
        $row['display_label'] = trim((string) ($row['label'] ?? '')) !== ''
            ? trim((string) $row['label'])
            : (string) ($row['original_filename'] ?? 'Fichier');
        $mime = strtolower(trim((string) ($row['mime'] ?? '')));
        $ext = strtolower((string) pathinfo((string) ($row['original_filename'] ?? ''), PATHINFO_EXTENSION));
        $row['is_pdf'] = $mime === 'application/pdf' || $ext === 'pdf';

        return $row;
    }

    /**
     * Normalise $_FILES['attachment_file'] (un fichier ou plusieurs via attachment_file[]).
     *
     * @param array<string, mixed>|null $filesField
     * @return list<array{name: string, tmp_name: string, size: int, error: int}>
     */
    public static function normalizeUploadedFiles(?array $filesField): array
    {
        if ($filesField === null || !isset($filesField['name'])) {
            return [];
        }

        $uploads = [];
        if (is_array($filesField['name'])) {
            $count = count($filesField['name']);
            for ($i = 0; $i < $count; $i++) {
                $error = (int) ($filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $uploads[] = [
                    'name' => (string) ($filesField['name'][$i] ?? 'fichier'),
                    'tmp_name' => (string) ($filesField['tmp_name'][$i] ?? ''),
                    'size' => (int) ($filesField['size'][$i] ?? 0),
                    'error' => $error,
                ];
            }
        } else {
            $error = (int) ($filesField['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $uploads[] = [
                    'name' => (string) ($filesField['name'] ?? 'fichier'),
                    'tmp_name' => (string) ($filesField['tmp_name'] ?? ''),
                    'size' => (int) ($filesField['size'] ?? 0),
                    'error' => $error,
                ];
            }
        }

        return array_values(array_filter(
            $uploads,
            static fn (array $upload): bool => (int) $upload['error'] === UPLOAD_ERR_OK
                && trim((string) $upload['tmp_name']) !== ''
        ));
    }

    private static function buildRelativePath(int $oeuvreId, string $originalName): string|false
    {
        if ($oeuvreId <= 0) {
            return false;
        }

        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $slug = self::slugify($base, 'fichier');
        $suffix = $ext !== '' ? '.' . preg_replace('/[^a-z0-9]+/', '', $ext) : '';
        $unique = substr(sha1($originalName . microtime(true)), 0, 8);

        // Dossier par œuvre catalogue (partagé), pas par exemplaire perso.
        return MediaStorage::relativePath('game', 'oeuvre-' . $oeuvreId, $slug . '-' . $unique . $suffix);
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
