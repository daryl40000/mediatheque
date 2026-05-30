<?php
/**
 * Façade : enregistrement fichier + ligne stored_objects.
 */

declare(strict_types=1);

namespace Moncine;

final class MediaStorageService
{
    private ObjectStorage $storage;

    private StoredObjectRepository $repo;

    public function __construct(
        ?ObjectStorage $storage = null,
        ?StoredObjectRepository $repo = null
    ) {
        $this->storage = $storage ?? new LocalFilesystemObjectStorage();
        $this->repo = $repo ?? new StoredObjectRepository();
    }

    /**
     * @return array{stored_object_id: int, relative_path: string}|string
     */
    public function storeBinary(string $kind, string $filename, string $binary, string $mime): array|string
    {
        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $relativePath = MediaStorage::relativePath($kind, $filename);
        if ($relativePath === '') {
            return 'Chemin de fichier invalide.';
        }

        try {
            $meta = $this->storage->put($relativePath, $binary, $mime);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        if (!StoredObjectRepository::tableExists()) {
            return [
                'stored_object_id' => 0,
                'relative_path' => $meta['relative_path'],
            ];
        }

        $row = $this->repo->create($meta['relative_path'], $meta['size_bytes'], $meta['mime']);
        if ($row === null) {
            return 'Impossible d’enregistrer les métadonnées du fichier.';
        }

        return [
            'stored_object_id' => $row['id'],
            'relative_path' => $row['relative_path'],
        ];
    }

    public function openReadStream(string $relativePath)
    {
        return $this->storage->readStream($relativePath);
    }

    /**
     * Supprime le fichier et la ligne stored_objects associée.
     * Si le fichier est déjà absent, la métadonnée est quand même nettoyée.
     */
    public function deleteByRelativePath(string $relativePath): bool
    {
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return false;
        }

        $row = StoredObjectRepository::tableExists()
            ? $this->repo->findByRelativePath($relativePath)
            : null;

        if ($this->storage->exists($relativePath) && !$this->storage->delete($relativePath)) {
            return false;
        }

        if ($row !== null) {
            $this->repo->deleteById((int) $row['id']);
        }

        return true;
    }
}
