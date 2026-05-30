<?php
/**
 * Stockage local sous MONCINE_MEDIA_PATH.
 */

declare(strict_types=1);

namespace Moncine;

use RuntimeException;

final class LocalFilesystemObjectStorage implements ObjectStorage
{
    public function put(string $relativePath, string $binary, string $mime): array
    {
        $absolute = MediaStorage::absolutePath($relativePath);
        if ($absolute === '') {
            throw new RuntimeException('Chemin média invalide.');
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier : ' . $dir);
        }

        if (file_put_contents($absolute, $binary) === false) {
            throw new RuntimeException('Écriture impossible : ' . $absolute);
        }

        @chmod($absolute, 0644);

        return [
            'relative_path' => $relativePath,
            'size_bytes' => strlen($binary),
            'mime' => $mime,
        ];
    }

    public function get(string $relativePath): ?string
    {
        $absolute = MediaStorage::absolutePath($relativePath);
        if ($absolute === '' || !is_file($absolute)) {
            return null;
        }

        $data = file_get_contents($absolute);

        return $data !== false ? $data : null;
    }

    public function readStream(string $relativePath)
    {
        $absolute = MediaStorage::absolutePath($relativePath);
        if ($absolute === '' || !is_file($absolute)) {
            return null;
        }

        $handle = fopen($absolute, 'rb');
        if ($handle === false) {
            return null;
        }

        return $handle;
    }

    public function delete(string $relativePath): bool
    {
        $absolute = MediaStorage::absolutePath($relativePath);
        if ($absolute === '' || !is_file($absolute)) {
            return true;
        }

        return @unlink($absolute);
    }

    public function exists(string $relativePath): bool
    {
        $absolute = MediaStorage::absolutePath($relativePath);

        return $absolute !== '' && is_file($absolute);
    }

    public function sizeBytes(string $relativePath): int
    {
        $absolute = MediaStorage::absolutePath($relativePath);
        if ($absolute === '' || !is_file($absolute)) {
            return 0;
        }

        return (int) filesize($absolute);
    }
}
