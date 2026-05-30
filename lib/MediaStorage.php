<?php
/**
 * Chemins médias sous MONCINE_MEDIA_PATH (racine unique configurable).
 *
 * Sous-dossiers standard (créés à la demande) :
 * - objects/   — fichiers binaires génériques (PDF, etc.)
 * - magazines/ — réservé phase 12–13
 * - books/     — réservé phase 11 (BD / livres)
 * - exports/  — PDF générés (phase 10)
 * - tmp/      — uploads temporaires
 */

declare(strict_types=1);

namespace Moncine;

final class MediaStorage
{
    public const SUBDIR_OBJECTS = 'objects';
    public const SUBDIR_MAGAZINES = 'magazines';
    public const SUBDIR_BOOKS = 'books';
    public const SUBDIR_EXPORTS = 'exports';
    public const SUBDIR_TMP = 'tmp';

    /** @var array<string, string> */
    public const SUBDIRS = [
        'object' => self::SUBDIR_OBJECTS,
        'magazine' => self::SUBDIR_MAGAZINES,
        'book' => self::SUBDIR_BOOKS,
        'export' => self::SUBDIR_EXPORTS,
        'tmp' => self::SUBDIR_TMP,
    ];

    public static function rootPath(): string
    {
        return MediaPathConfig::effectiveRootPath();
    }

    public static function subdirPath(string $kind): string
    {
        $key = strtolower(trim($kind));
        $subdir = self::SUBDIRS[$key] ?? self::SUBDIR_OBJECTS;

        return self::rootPath() . '/' . $subdir;
    }

    /**
     * Chemin relatif sous la racine (sans ..), pour stockage en base.
     */
    public static function relativePath(string $kind, string ...$segments): string
    {
        $parts = [self::SUBDIRS[strtolower(trim($kind))] ?? self::SUBDIR_OBJECTS];
        foreach ($segments as $seg) {
            $seg = trim((string) $seg, '/\\');
            if ($seg !== '' && $seg !== '.') {
                $parts[] = $seg;
            }
        }

        return implode('/', $parts);
    }

    public static function absolutePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return '';
        }

        return self::rootPath() . '/' . $relativePath;
    }

    /**
     * Crée la racine et les sous-dossiers standard si besoin.
     *
     * @return true|string
     */
    public static function ensureLayout(): bool|string
    {
        $root = self::rootPath();
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            return 'Impossible de créer le dossier médias : ' . $root;
        }

        foreach (array_unique(array_values(self::SUBDIRS)) as $subdir) {
            $path = $root . '/' . $subdir;
            if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
                return 'Impossible de créer : ' . $path;
            }
        }

        return true;
    }

    public static function isInsideRoot(string $absolutePath): bool
    {
        $root = realpath(self::rootPath());
        $file = realpath($absolutePath);
        if ($root === false || $file === false) {
            return false;
        }

        return str_starts_with($file, $root . DIRECTORY_SEPARATOR);
    }
}
