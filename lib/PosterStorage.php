<?php
/**
 * Télécharge et stocke les affiches dans MONCINE_DATA/posters/ (chemins web /posters/…).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class PosterStorage
{
    public const WEB_PREFIX = '/posters';

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function postersFilesystemDir(): string
    {
        return MONCINE_POSTERS_DIR;
    }

    /** Ancien emplacement (www/posters/) — repli lecture / suppression après migration. */
    public static function legacyPostersFilesystemDir(): string
    {
        return MONCINE_WWW . '/posters';
    }

    public static function isLocalWebPath(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        return self::parseLocalWebPath($path) !== null;
    }

    /**
     * URL pour afficher une affiche locale dans le navigateur (poster.php).
     */
    public static function deliveryUrlFromWeb(string $webPath): string
    {
        $parsed = self::parseLocalWebPath($webPath);
        if ($parsed === null) {
            return '';
        }

        return '/poster.php?' . http_build_query($parsed, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{id?: int, series?: int, ext: string}|null
     */
    private static function parseLocalWebPath(string $path): ?array
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match(
            '#^' . preg_quote(self::WEB_PREFIX, '#') . '/s(\d+)\.(jpe?g|png|webp)$#i',
            $path,
            $m
        )) {
            $ext = strtolower($m[2]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }

            return ['series' => (int) $m[1], 'ext' => $ext];
        }

        if (!preg_match(
            '#^' . preg_quote(self::WEB_PREFIX, '#') . '/(\d+)\.(jpe?g|png|webp)$#i',
            $path,
            $m
        )) {
            return null;
        }

        $ext = strtolower($m[2]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return ['id' => (int) $m[1], 'ext' => $ext];
    }

    public static function isRemoteUrl(string $url): bool
    {
        return SecureUrl::isHttpsUrl($url);
    }

    /** Chemin web local pour une série magazine (logo / couverture type). */
    public static function webPathForSeries(int $seriesId, string $extension): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg');

        return self::WEB_PREFIX . '/s' . $seriesId . '.' . $ext;
    }

    /** Chemin web local pour une œuvre (fichier peut ne pas exister encore). */
    public static function webPathForOeuvre(int $oeuvreId, string $extension): string
    {
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $extension) ?: 'jpg');

        return self::WEB_PREFIX . '/' . $oeuvreId . '.' . $ext;
    }

    /**
     * Télécharge une URL HTTPS et enregistre l’affiche pour l’œuvre.
     * Retourne le chemin web local (/posters/123.jpg) ou '' en cas d’échec.
     */
    public function cacheRemoteForOeuvre(int $oeuvreId, string $remoteUrl): string
    {
        if ($oeuvreId <= 0 || !self::isRemoteUrl($remoteUrl)) {
            return '';
        }

        self::ensureDirectory();

        $binary = $this->download($remoteUrl);
        if ($binary === null) {
            return '';
        }

        $mime = $this->detectImageMime($binary);
        if ($mime === null) {
            return '';
        }

        $ext = self::ALLOWED_MIME[$mime];
        $webPath = self::webPathForOeuvre($oeuvreId, $ext);

        $this->removeLocalFilesForOeuvre($oeuvreId);

        $filePath = self::postersFilesystemDir() . '/' . $oeuvreId . '.' . $ext;
        if (@file_put_contents($filePath, $binary) === false) {
            return '';
        }

        @chmod($filePath, 0644);

        return $webPath;
    }

    /**
     * Enregistre une image locale pour une œuvre (import ZIP ou copie manuelle).
     * Met à jour le fichier sur disque et retourne le chemin web (/posters/…).
     */
    public function importBinaryForOeuvre(int $oeuvreId, string $binary): string
    {
        if ($oeuvreId <= 0 || $binary === '') {
            return '';
        }

        $maxBytes = defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 2_097_152;
        if (strlen($binary) > $maxBytes) {
            return '';
        }

        $mime = $this->detectImageMime($binary);
        if ($mime === null) {
            return '';
        }

        self::ensureDirectory();
        $ext = self::ALLOWED_MIME[$mime];
        $webPath = self::webPathForOeuvre($oeuvreId, $ext);
        $this->removeLocalFilesForOeuvre($oeuvreId);

        $filePath = self::postersFilesystemDir() . '/' . $oeuvreId . '.' . $ext;
        if (@file_put_contents($filePath, $binary) === false) {
            return '';
        }

        @chmod($filePath, 0644);

        return $webPath;
    }

    /**
     * Enregistre une image locale pour une série magazine (logo ou couverture type).
     */
    public function importBinaryForSeries(int $seriesId, string $binary): string
    {
        if ($seriesId <= 0 || $binary === '') {
            return '';
        }

        $maxBytes = defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 2_097_152;
        if (strlen($binary) > $maxBytes) {
            return '';
        }

        $mime = $this->detectImageMime($binary);
        if ($mime === null) {
            return '';
        }

        self::ensureDirectory();
        $ext = self::ALLOWED_MIME[$mime];
        $webPath = self::webPathForSeries($seriesId, $ext);
        $this->removeLocalFilesForSeries($seriesId);

        $filePath = self::postersFilesystemDir() . '/s' . $seriesId . '.' . $ext;
        if (@file_put_contents($filePath, $binary) === false) {
            return '';
        }

        @chmod($filePath, 0644);

        return $webPath;
    }

    /**
     * Si l’URL est distante, la télécharge ; si déjà locale, la conserve.
     */
    public function ensureLocalForOeuvre(int $oeuvreId, string $posterUrl): string
    {
        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return '';
        }

        if (self::isLocalWebPath($posterUrl)) {
            $filePath = self::filesystemPathFromWeb($posterUrl);
            if ($filePath !== null && is_file($filePath)) {
                return $posterUrl;
            }

            return '';
        }

        if (!self::isRemoteUrl($posterUrl)) {
            return '';
        }

        return $this->cacheRemoteForOeuvre($oeuvreId, $posterUrl);
    }

    /**
     * Télécharge par lots les affiches encore hébergées chez TMDB (ou autre HTTPS).
     *
     * @return array{downloaded: int, failed: int, remaining: int, errors: list<string>}
     */
    public function migrateRemoteBatch(int $limit = 15): array
    {
        $limit = max(1, min(40, $limit));
        $db = Database::getInstance();

        if (!CatalogSchema::usesCatalogTables($db)) {
            return $this->migrateLegacyFilmsBatch($db, $limit);
        }

        $stmt = $db->prepare(
            'SELECT id, poster_url FROM oeuvres
             WHERE TRIM(poster_url) != ""
               AND LOWER(poster_url) LIKE \'https://%\'
             ORDER BY id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $this->processMigrationRows($stmt->fetchAll(), $db);
    }

    public function countRemotePosters(): int
    {
        $db = Database::getInstance();

        if (!CatalogSchema::usesCatalogTables($db)) {
            return (int) $db->query(
                'SELECT COUNT(*) FROM films
                 WHERE TRIM(poster_url) != "" AND LOWER(poster_url) LIKE \'https://%\''
            )->fetchColumn();
        }

        return (int) $db->query(
            'SELECT COUNT(*) FROM oeuvres
             WHERE TRIM(poster_url) != "" AND LOWER(poster_url) LIKE \'https://%\''
        )->fetchColumn();
    }

    public function countLocalPosters(): int
    {
        $db = Database::getInstance();

        if (!CatalogSchema::usesCatalogTables($db)) {
        return (int) $db->query(
            "SELECT COUNT(*) FROM films WHERE poster_url LIKE '/posters/%'"
        )->fetchColumn();
        }

        return (int) $db->query(
            "SELECT COUNT(*) FROM oeuvres WHERE poster_url LIKE '/posters/%'"
        )->fetchColumn();
    }

    public static function filesystemPathFromWeb(string $webPath): ?string
    {
        if (!self::isLocalWebPath($webPath)) {
            return null;
        }

        $name = basename($webPath);
        if (!preg_match('/^(s\d+|\d+)\.(jpe?g|png|webp)$/i', $name)) {
            return null;
        }

        foreach (self::posterSearchDirs() as $dir) {
            $resolved = self::resolveFileInDir($dir, $name);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function deleteLocalForOeuvre(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }
        $this->removeLocalFilesForOeuvre($oeuvreId);
    }

    private static function ensureDirectory(): void
    {
        $dir = self::postersFilesystemDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /** @return list<string> */
    private static function posterSearchDirs(): array
    {
        return [self::postersFilesystemDir(), self::legacyPostersFilesystemDir()];
    }

    private static function resolveFileInDir(string $dir, string $basename): ?string
    {
        $path = $dir . '/' . $basename;
        $base = realpath($dir);
        if ($base === false) {
            return is_file($path) ? $path : null;
        }

        $resolved = realpath($path);
        if ($resolved !== false && str_starts_with($resolved, $base)) {
            return $resolved;
        }

        return is_file($path) ? $path : null;
    }

    private function removeLocalFilesForOeuvre(int $oeuvreId): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $name = $oeuvreId . '.' . $ext;
            foreach (self::posterSearchDirs() as $dir) {
                $path = $dir . '/' . $name;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function removeLocalFilesForSeries(int $seriesId): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $name = 's' . $seriesId . '.' . $ext;
            foreach (self::posterSearchDirs() as $dir) {
                $path = $dir . '/' . $name;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function download(string $url): ?string
    {
        $maxBytes = defined('MONCINE_POSTER_MAX_BYTES') ? (int) MONCINE_POSTER_MAX_BYTES : 2_097_152;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'Moncine/1.0 (poster cache)',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
            if (strlen($body) > $maxBytes) {
                return null;
            }

            return $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'user_agent' => 'Moncine/1.0 (poster cache)',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || strlen($body) > $maxBytes) {
            return null;
        }

        return $body;
    }

    private function detectImageMime(string $binary): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $binary);
                finfo_close($finfo);
                if (is_string($mime) && isset(self::ALLOWED_MIME[$mime])) {
                    return $mime;
                }
            }
        }

        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{downloaded: int, failed: int, remaining: int, errors: list<string>}
     */
    private function processMigrationRows(array $rows, PDO $db): array
    {
        $downloaded = 0;
        $failed = 0;
        $errors = [];
        $updateOeuvre = $db->prepare('UPDATE oeuvres SET poster_url = ? WHERE id = ?');
        $updateFilm = $db->prepare('UPDATE films SET poster_url = ? WHERE id = ?');

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $remote = trim((string) ($row['poster_url'] ?? ''));
            if ($id <= 0 || $remote === '') {
                continue;
            }

            $local = $this->cacheRemoteForOeuvre($id, $remote);
            if ($local === '') {
                $failed++;
                $errors[] = 'Échec œuvre #' . $id;
                continue;
            }

            if (CatalogSchema::usesCatalogTables($db)) {
                $updateOeuvre->execute([$local, $id]);
            } else {
                $updateFilm->execute([$local, $id]);
            }
            $downloaded++;
        }

        return [
            'downloaded' => $downloaded,
            'failed' => $failed,
            'remaining' => max(0, $this->countRemotePosters()),
            'errors' => array_slice($errors, 0, 8),
        ];
    }

    /**
     * @return array{downloaded: int, failed: int, remaining: int, errors: list<string>}
     */
    private function migrateLegacyFilmsBatch(PDO $db, int $limit): array
    {
        $stmt = $db->prepare(
            'SELECT id, poster_url FROM films
             WHERE TRIM(poster_url) != ""
               AND LOWER(poster_url) LIKE \'https://%\'
             ORDER BY id ASC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $downloaded = 0;
        $failed = 0;
        $errors = [];
        $update = $db->prepare('UPDATE films SET poster_url = ? WHERE id = ?');

        foreach ($stmt->fetchAll() as $row) {
            $id = (int) ($row['id'] ?? 0);
            $remote = trim((string) ($row['poster_url'] ?? ''));
            $local = $this->cacheRemoteForOeuvre($id, $remote);
            if ($local === '') {
                $failed++;
                $errors[] = 'Échec film #' . $id;
                continue;
            }
            $update->execute([$local, $id]);
            $downloaded++;
        }

        return [
            'downloaded' => $downloaded,
            'failed' => $failed,
            'remaining' => max(0, $this->countRemotePosters()),
            'errors' => array_slice($errors, 0, 8),
        ];
    }
}
