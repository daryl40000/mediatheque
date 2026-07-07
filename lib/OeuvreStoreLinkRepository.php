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
     * @return list<array<string, mixed>>
     */
    public function listPendingReview(int $limit = 50): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            'SELECT osl.*, o.titre AS oeuvre_titre
             FROM oeuvre_store_links osl
             INNER JOIN oeuvres o ON o.id = osl.oeuvre_id
             WHERE osl.manually_verified = 0
               AND osl.match_confidence IS NOT NULL
               AND osl.match_confidence >= ?
               AND osl.match_confidence < ?
             ORDER BY osl.match_confidence DESC, o.titre COLLATE FRENCH_NOCASE
             LIMIT ?'
        );
        $stmt->bindValue(1, StoreLinkMatcher::MIN_STORE_THRESHOLD);
        $stmt->bindValue(2, StoreLinkMatcher::AUTO_VERIFY_THRESHOLD);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPendingReview(): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM oeuvre_store_links
             WHERE manually_verified = 0
               AND match_confidence IS NOT NULL
               AND match_confidence >= ?
               AND match_confidence < ?'
        );
        $stmt->execute([
            StoreLinkMatcher::MIN_STORE_THRESHOLD,
            StoreLinkMatcher::AUTO_VERIFY_THRESHOLD,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countNeedingEnrichment(string $store, bool $onlyMissing = true): int
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return 0;
        }

        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return 0;
        }

        $missingSql = $onlyMissing
            ? 'AND NOT EXISTS (
                SELECT 1 FROM oeuvre_store_links osl
                WHERE osl.oeuvre_id = o.id AND osl.store = :store AND osl.manually_verified = 1
            )'
            : '';

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :media_domain
             ' . $missingSql
        );
        $params = ['media_domain' => MediaDomain::JEU, 'store' => $store];
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichment(string $store, int $limit, bool $onlyMissing = true): array
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return [];
        }

        $store = GameDigitalStore::normalizeStoreKey($store);
        if ($store === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $missingSql = $onlyMissing
            ? 'AND NOT EXISTS (
                SELECT 1 FROM oeuvre_store_links osl
                WHERE osl.oeuvre_id = o.id AND osl.store = :store AND osl.manually_verified = 1
            )'
            : '';

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, oj.digital_stores
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :media_domain
             ' . $missingSql . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE
             LIMIT :lim'
        );
        $stmt->bindValue(':media_domain', MediaDomain::JEU);
        $stmt->bindValue(':store', $store);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
     * @param list<string> $stores
     * @return list<array<string, mixed>>
     */
    public function findNeedingEnrichmentAny(array $stores, int $limit, bool $onlyMissing = true): array
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return [];
        }

        $stores = array_values(array_filter(array_map(
            static fn (string $store): string => GameDigitalStore::normalizeStoreKey($store),
            $stores
        )));
        if ($stores === []) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        if (!$onlyMissing) {
            return $this->findCatalogSample($limit);
        }

        $orParts = [];
        $params = ['media_domain' => MediaDomain::JEU];
        foreach ($stores as $i => $store) {
            $key = 'store' . $i;
            $orParts[] = 'NOT EXISTS (
                SELECT 1 FROM oeuvre_store_links osl
                WHERE osl.oeuvre_id = o.id AND osl.store = :' . $key . ' AND osl.manually_verified = 1
            )';
            $params[$key] = $store;
        }

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, oj.digital_stores
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :media_domain
               AND (' . implode(' OR ', $orParts) . ')
             ORDER BY o.titre COLLATE FRENCH_NOCASE
             LIMIT :lim'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findCatalogSample(int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, oj.digital_stores
             FROM oeuvres o
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :media_domain
             ORDER BY o.titre COLLATE FRENCH_NOCASE
             LIMIT :lim'
        );
        $stmt->bindValue(':media_domain', MediaDomain::JEU);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            'SELECT store, store_url FROM oeuvre_store_links
             WHERE oeuvre_id = ? AND manually_verified = 1 AND TRIM(store_url) != \'\''
        );
        $stmt->execute([$oeuvreId]);
        $urls = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $store = GameDigitalStore::normalizeStoreKey((string) ($row['store'] ?? ''));
            $url = SecureUrl::sanitizePosterUrl(trim((string) ($row['store_url'] ?? '')));
            if ($store !== '' && $url !== '') {
                $urls[$store] = $url;
            }
        }

        return $urls;
    }

    public function markVerified(int $oeuvreId, string $store, bool $syncDigitalStores = false): bool
    {
        $row = $this->find($oeuvreId, $store);
        if ($row === null) {
            return false;
        }

        $this->db->prepare(
            'UPDATE oeuvre_store_links
             SET manually_verified = 1, last_verified_at = datetime(\'now\'), updated_at = datetime(\'now\')
             WHERE oeuvre_id = ? AND store = ?'
        )->execute([$oeuvreId, GameDigitalStore::normalizeStoreKey($store)]);

        return true;
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
