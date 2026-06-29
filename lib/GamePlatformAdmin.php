<?php
/**
 * Administration des plateformes jeux (CRUD).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GamePlatformAdmin
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return true|string
     */
    public function create(
        string $platformKey,
        string $label,
        string $shortLabel,
        string $kind,
        string $consoleStore,
        int $sortOrder
    ): bool|string {
        if (!GamePlatformRegistry::isAvailable()) {
            return 'Table plateformes non disponible (migration en attente).';
        }

        $key = $this->normalizeKey($platformKey);
        if ($key === '') {
            return 'Identifiant de plateforme invalide (lettres minuscules, chiffres, tirets).';
        }

        $label = trim($label);
        if ($label === '') {
            return 'Le libellé est obligatoire.';
        }

        $kind = $this->normalizeKind($kind);
        $consoleStore = $this->normalizeConsoleStore($consoleStore, $kind);
        $shortLabel = trim($shortLabel);
        if ($shortLabel === '') {
            $shortLabel = $label;
        }

        if ($this->findKey($key) !== null) {
            return 'Cette plateforme existe déjà.';
        }

        try {
            $this->db->prepare(
                'INSERT INTO game_platform (platform_key, label, short_label, kind, console_store, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([$key, $label, $shortLabel, $kind, $consoleStore, max(0, $sortOrder)]);
        } catch (\Throwable) {
            return 'Impossible d’ajouter la plateforme.';
        }

        GamePlatformRegistry::resetCache();

        return true;
    }

    /**
     * @return true|string
     */
    public function update(
        string $platformKey,
        string $label,
        string $shortLabel,
        string $kind,
        string $consoleStore,
        int $sortOrder,
        bool $active
    ): bool|string {
        if (!GamePlatformRegistry::isAvailable()) {
            return 'Table plateformes non disponible.';
        }

        $key = $this->normalizeKey($platformKey);
        if ($key === '' || $this->findKey($key) === null) {
            return 'Plateforme introuvable.';
        }

        $label = trim($label);
        if ($label === '') {
            return 'Le libellé est obligatoire.';
        }

        $kind = $this->normalizeKind($kind);
        $consoleStore = $this->normalizeConsoleStore($consoleStore, $kind);
        $shortLabel = trim($shortLabel);
        if ($shortLabel === '') {
            $shortLabel = $label;
        }

        $this->db->prepare(
            'UPDATE game_platform
             SET label = ?, short_label = ?, kind = ?, console_store = ?, sort_order = ?, active = ?
             WHERE platform_key = ?'
        )->execute([
            $label,
            $shortLabel,
            $kind,
            $consoleStore,
            max(0, $sortOrder),
            $active ? 1 : 0,
            $key,
        ]);

        GamePlatformRegistry::resetCache();

        return true;
    }

    public function countUsages(string $platformKey): int
    {
        $key = $this->normalizeKey($platformKey);
        if ($key === '') {
            return 0;
        }

        $like = '%,' . $key . ',%';
        $count = 0;

        if (GameSchema::hasPlatformsColumn()) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM oeuvre_jeu
                 WHERE platform = ? OR (',' || REPLACE(COALESCE(platforms, ''), ' ', '') || ',') LIKE ?"
            );
            $stmt->execute([$key, $like]);
            $count += (int) $stmt->fetchColumn();
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM oeuvre_jeu WHERE platform = ?');
            $stmt->execute([$key]);
            $count += (int) $stmt->fetchColumn();
        }

        if (GameSchema::hasOwnedPlatformsColumn()) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM bibliotheque
                 WHERE (',' || REPLACE(COALESCE(owned_platforms, ''), ' ', '') || ',') LIKE ?"
            );
            $stmt->execute([$like]);
            $count += (int) $stmt->fetchColumn();
        }

        return $count;
    }

 
    /** @return array<string, string> */
    public static function kindChoices(): array
    {
        return [
            'pc' => 'PC',
            'console' => 'Console',
            'mobile' => 'Mobile',
            'multi' => 'Multi-plateformes',
            'other' => 'Autre',
        ];
    }

    /** @return array<string, string> */
    public static function consoleStoreChoices(): array
    {
        return [
            '' => '—',
            'psn' => 'PlayStation Store',
            'xbox' => 'Microsoft Store / Xbox',
            'eshop' => 'Nintendo eShop',
        ];
    }

    private function findKey(string $key): ?string
    {
        $stmt = $this->db->prepare('SELECT platform_key FROM game_platform WHERE platform_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetchColumn();

        return $row !== false ? (string) $row : null;
    }

    private function normalizeKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');
        $raw = preg_replace('/[^a-z0-9_]+/', '_', $raw) ?? '';
        $raw = trim($raw, '_');

        if ($raw === '' || strlen($raw) > 32) {
            return '';
        }

        return $raw;
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return isset(self::kindChoices()[$kind]) ? $kind : 'other';
    }

    private function normalizeConsoleStore(string $store, string $kind): string
    {
        $store = strtolower(trim($store));
        if ($kind !== 'console') {
            return '';
        }

        return isset(self::consoleStoreChoices()[$store]) ? $store : '';
    }
}
