<?php
/**
 * Abstraction stockage de fichiers locaux (phase 9).
 */

declare(strict_types=1);

namespace Moncine;

interface ObjectStorage
{
    /**
     * Enregistre un fichier (écrase si existe).
     *
     * @return array{relative_path: string, size_bytes: int, mime: string}
     */
    public function put(string $relativePath, string $binary, string $mime): array;

    /**
     * Lit le fichier entier (attention mémoire pour gros PDF).
     */
    public function get(string $relativePath): ?string;

    /**
     * Ouvre un flux lecture (streaming).
     *
     * @return resource|null
     */
    public function readStream(string $relativePath);

    public function delete(string $relativePath): bool;

    public function exists(string $relativePath): bool;

    public function sizeBytes(string $relativePath): int;
}
