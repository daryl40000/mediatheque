<?php
/**
 * Liens magasins enrichis sur les fiches catalogue jeux.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class OeuvreStoreLinkRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameSchema::oeuvreStoreLinksTableExists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $oeuvreId, string $store): ?array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM oeuvre_store_links WHERE oeuvre_id = ? AND store = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreId, $store]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{store_slug?: string, store_url?: string, store_title?: string, match_confidence?: ?float, manually_verified?: bool} $data
     */
    public function upsert(int $oeuvreId, string $store, array $data): void
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return;
        }

        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return;
        }

        $slug = trim((string) ($data['store_slug'] ?? ''));
        $url = SecureUrl::sanitizePosterUrl(trim((string) ($data['store_url'] ?? '')));
        $storeTitle = trim((string) ($data['store_title'] ?? ''));
        $confidence = array_key_exists('match_confidence', $data) ? $data['match_confidence'] : null;
        $verified = !empty($data['manually_verified']) ? 1 : 0;
        $verifiedAt = $verified ? date('Y-m-d H:i:s') : null;

        $this->db->prepare(
            'INSERT INTO oeuvre_store_links (
                oeuvre_id, store, store_slug, store_url, store_title,
                match_confidence, manually_verified, last_verified_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))
             ON CONFLICT(oeuvre_id, store) DO UPDATE SET
                store_slug = excluded.store_slug,
                store_url = excluded.store_url,
                store_title = excluded.store_title,
                match_confidence = excluded.match_confidence,
                manually_verified = CASE
                    WHEN oeuvre_store_links.manually_verified = 1 AND excluded.manually_verified = 0
                    THEN oeuvre_store_links.manually_verified
                    ELSE excluded.manually_verified
                END,
                last_verified_at = COALESCE(excluded.last_verified_at, oeuvre_store_links.last_verified_at),
                updated_at = datetime(\'now\')'
        )->execute([
            $oeuvreId,
            $store,
            $slug,
            $url,
            $storeTitle,
            $confidence,
            $verified,
            $verifiedAt,
        ]);
    }

    /**
     * @return array<string, string> store => URL (liens catalogue validés uniquement)
     */
    public function listVerifiedUrlsForOeuvre(int $oeuvreId): array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT store, store_slug, store_url FROM oeuvre_store_links
             WHERE oeuvre_id = ? AND manually_verified = 1'
        );
        $stmt->execute([$oeuvreId]);
        $urls = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $store = GameDigitalStore::normalizeStoreKey((string) ($row['store'] ?? ''));
            if ($store === '') {
                continue;
            }

            $url = SecureUrl::sanitizePosterUrl(trim((string) ($row['store_url'] ?? '')));
            if ($url === '') {
                $slug = trim((string) ($row['store_slug'] ?? ''));
                $url = CatalogGameStoreLinks::urlFromSlug($store, $slug);
            }

            if ($url !== '') {
                $urls[$store] = $url;
            }
        }

        return $urls;
    }

    public function delete(int $oeuvreId, string $store): void
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return;
        }

        $this->db->prepare('DELETE FROM oeuvre_store_links WHERE oeuvre_id = ? AND store = ?')
            ->execute([$oeuvreId, GameDigitalStore::normalizeStoreKey($store)]);
    }

    public function reassignOnOeuvreMerge(int $keepOeuvreId, int $removeOeuvreId): void
    {
        if (!self::isAvailable() || $keepOeuvreId <= 0 || $removeOeuvreId <= 0 || $keepOeuvreId === $removeOeuvreId) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_store_links SET oeuvre_id = ? WHERE oeuvre_id = ?'
        )->execute([$keepOeuvreId, $removeOeuvreId]);
    }
}
