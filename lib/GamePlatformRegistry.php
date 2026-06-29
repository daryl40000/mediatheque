<?php
/**
 * Plateformes jeux en base (admin) avec repli sur la liste intégrée.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GamePlatformRegistry
{
    /** @var array<string, array{label: string, short_label: string, kind: string, console_store: string, active: bool, sort_order: int}>|null */
    private static ?array $indexedCache = null;

    public static function resetCache(): void
    {
        self::$indexedCache = null;
    }

    public static function isAvailable(): bool
    {
        return GameSchema::hasGamePlatformTable();
    }

    /** @return array<string, string> clé => libellé */
    public static function choices(bool $activeOnly = true): array
    {
        $out = [];
        foreach (self::indexed($activeOnly) as $key => $meta) {
            $out[$key] = $meta['label'];
        }

        return $out;
    }

    public static function isValid(?string $key, bool $activeOnly = false): bool
    {
        $key = GamePlatform::normalizeKey((string) $key);
        if ($key === '') {
            return false;
        }

        return isset(self::indexed($activeOnly)[$key]);
    }

    public static function label(?string $key): string
    {
        $key = GamePlatform::normalizeKey((string) $key);
        if ($key === '') {
            return '';
        }

        return self::indexed(false)[$key]['label'] ?? $key;
    }

    public static function shortLabel(?string $key): string
    {
        $key = GamePlatform::normalizeKey((string) $key);
        if ($key === '') {
            return '';
        }

        $meta = self::indexed(false)[$key] ?? null;
        if ($meta === null) {
            return $key;
        }

        $short = trim($meta['short_label']);

        return $short !== '' ? $short : $meta['label'];
    }

    public static function kind(?string $key): string
    {
        $key = GamePlatform::normalizeKey((string) $key);
        if ($key === '') {
            return 'other';
        }

        return self::indexed(false)[$key]['kind'] ?? 'other';
    }

    public static function isConsole(string $platform): bool
    {
        return self::kind($platform) === 'console';
    }

    public static function usesPcDigitalStores(string $platform): bool
    {
        return self::kind($platform) === 'pc';
    }

    public static function consoleStoreForPlatform(string $platform): ?string
    {
        $key = GamePlatform::normalizeKey($platform);
        if ($key === '') {
            return null;
        }

        $store = trim(self::indexed(false)[$key]['console_store'] ?? '');

        return $store !== '' ? $store : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForAdmin(): array
    {
        if (!self::isAvailable()) {
            return self::builtinRowsForAdmin();
        }

        $stmt = Database::getInstance()->query(
            'SELECT platform_key, label, short_label, kind, console_store, sort_order, active
             FROM game_platform
             ORDER BY sort_order ASC, label COLLATE NOCASE ASC'
        );
        if ($stmt === false) {
            return self::builtinRowsForAdmin();
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $rows[] = [
                'platform_key' => (string) ($row['platform_key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'short_label' => (string) ($row['short_label'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'other'),
                'console_store' => (string) ($row['console_store'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'active' => !empty($row['active']),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{label: string, short_label: string, kind: string, console_store: string, active: bool, sort_order: int}>
     */
    private static function indexed(bool $activeOnly): array
    {
        $all = self::loadIndexed();
        if (!$activeOnly) {
            return $all;
        }

        return array_filter($all, static fn (array $meta): bool => !empty($meta['active']));
    }

    /**
     * @return array<string, array{label: string, short_label: string, kind: string, console_store: string, active: bool, sort_order: int}>
     */
    private static function loadIndexed(): array
    {
        if (self::$indexedCache !== null) {
            return self::$indexedCache;
        }

        if (!self::isAvailable()) {
            return self::$indexedCache = self::builtinIndexed();
        }

        $stmt = Database::getInstance()->query(
            'SELECT platform_key, label, short_label, kind, console_store, sort_order, active
             FROM game_platform
             ORDER BY sort_order ASC, platform_key ASC'
        );
        if ($stmt === false) {
            return self::$indexedCache = self::builtinIndexed();
        }

        $indexed = self::builtinIndexed();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = GamePlatform::normalizeKey((string) ($row['platform_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $indexed[$key] = [
                'label' => trim((string) ($row['label'] ?? '')) ?: $key,
                'short_label' => trim((string) ($row['short_label'] ?? '')),
                'kind' => (string) ($row['kind'] ?? 'other'),
                'console_store' => trim((string) ($row['console_store'] ?? '')),
                'active' => !empty($row['active']),
                'sort_order' => (int) ($row['sort_order'] ?? 100),
            ];
        }

        return self::$indexedCache = $indexed;
    }

    /**
     * @return array<string, array{label: string, short_label: string, kind: string, console_store: string, active: bool, sort_order: int}>
     */
    private static function builtinIndexed(): array
    {
        return [
            GamePlatform::PC => ['label' => 'PC', 'short_label' => 'PC', 'kind' => 'pc', 'console_store' => '', 'active' => true, 'sort_order' => 10],
            GamePlatform::PS5 => ['label' => 'PlayStation 5', 'short_label' => 'PS5', 'kind' => 'console', 'console_store' => 'psn', 'active' => true, 'sort_order' => 20],
            GamePlatform::PS4 => ['label' => 'PlayStation 4', 'short_label' => 'PS4', 'kind' => 'console', 'console_store' => 'psn', 'active' => true, 'sort_order' => 30],
            GamePlatform::XBOX_SERIES => ['label' => 'Xbox Series', 'short_label' => 'Xbox Series', 'kind' => 'console', 'console_store' => 'xbox', 'active' => true, 'sort_order' => 40],
            GamePlatform::XBOX_ONE => ['label' => 'Xbox One', 'short_label' => 'Xbox One', 'kind' => 'console', 'console_store' => 'xbox', 'active' => true, 'sort_order' => 50],
            GamePlatform::SWITCH => ['label' => 'Nintendo Switch', 'short_label' => 'Switch', 'kind' => 'console', 'console_store' => 'eshop', 'active' => true, 'sort_order' => 60],
            GamePlatform::SWITCH2 => ['label' => 'Nintendo Switch 2', 'short_label' => 'Switch 2', 'kind' => 'console', 'console_store' => 'eshop', 'active' => true, 'sort_order' => 65],
            GamePlatform::MOBILE => ['label' => 'Mobile', 'short_label' => 'Mobile', 'kind' => 'mobile', 'console_store' => '', 'active' => true, 'sort_order' => 70],
            GamePlatform::MULTI => ['label' => 'Multi-plateformes', 'short_label' => 'Multi', 'kind' => 'multi', 'console_store' => '', 'active' => true, 'sort_order' => 80],
            GamePlatform::OTHER => ['label' => 'Autre', 'short_label' => 'Autre', 'kind' => 'other', 'console_store' => '', 'active' => true, 'sort_order' => 90],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function builtinRowsForAdmin(): array
    {
        $rows = [];
        foreach (self::builtinIndexed() as $key => $meta) {
            $rows[] = array_merge(['platform_key' => $key], $meta);
        }

        return $rows;
    }
}
