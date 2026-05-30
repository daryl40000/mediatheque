<?php
/**
 * Métadonnées des fichiers stockés localement (table stored_objects).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class StoredObjectRepository
{
    public const BACKEND_LOCAL = 'local';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'stored_objects' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @return array{relative_path: string, size_bytes: int, mime: string, id: int}|null
     */
    public function create(string $relativePath, int $sizeBytes, string $mime, string $checksum = ''): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $mime = trim($mime);
        if ($relativePath === '') {
            return null;
        }

        $this->db->prepare(
            'INSERT INTO stored_objects (backend, relative_path, mime, size_bytes, checksum)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([self::BACKEND_LOCAL, $relativePath, $mime, max(0, $sizeBytes), $checksum]);

        $id = (int) $this->db->lastInsertId();

        return [
            'id' => $id,
            'relative_path' => $relativePath,
            'size_bytes' => $sizeBytes,
            'mime' => $mime,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM stored_objects WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function deleteById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM stored_objects WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findByRelativePath(string $relativePath): ?array
    {
        if (!self::tableExists()) {
            return null;
        }
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $stmt = $this->db->prepare('SELECT * FROM stored_objects WHERE relative_path = ? LIMIT 1');
        $stmt->execute([$relativePath]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
