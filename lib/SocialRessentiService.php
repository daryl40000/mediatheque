<?php
/**
 * Ressentis des membres du foyer et des amis sur une même œuvre catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SocialRessentiService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @return array{
     *   foyer: list<array{user_id: int, display_name: string, ressenti_score: ?int, is_self: bool}>,
     *   friends: list<array{user_id: int, display_name: string, ressenti_score: ?int, is_self: bool}>
     * }
     */
    public function listAroundOeuvre(int $oeuvreId, string $mediaDomain, int $viewerUserId, int $viewerFoyerId): array
    {
        if ($oeuvreId <= 0 || $viewerUserId <= 0) {
            return ['foyer' => [], 'friends' => []];
        }

        $domain = MediaDomain::normalize($mediaDomain);
        $scoresByUser = $this->fetchBestScoresByUser($oeuvreId, $domain);

        $foyer = [];
        if ($viewerFoyerId > 0) {
            foreach ((new FoyerRepository())->listMembers($viewerFoyerId) as $member) {
                $userId = (int) ($member['id'] ?? $member['user_id'] ?? 0);
                if ($userId <= 0) {
                    continue;
                }
                $foyer[] = $this->entryForUser($member, $userId, $viewerUserId, $scoresByUser);
            }
        }

        $friends = [];
        if (FriendshipRepository::isAvailable()) {
            foreach ((new FriendshipRepository())->listFriends($viewerUserId) as $friend) {
                $userId = (int) ($friend['id'] ?? 0);
                if ($userId <= 0 || $userId === $viewerUserId) {
                    continue;
                }
                if ($viewerFoyerId > 0 && $this->isFoyerMember($foyer, $userId)) {
                    continue;
                }
                $friends[] = $this->entryForUser($friend, $userId, $viewerUserId, $scoresByUser);
            }
        }

        return ['foyer' => $foyer, 'friends' => $friends];
    }

    /**
     * @param array<string, mixed> $userRow
     * @param array<int, int> $scoresByUser
     * @return array{user_id: int, display_name: string, ressenti_score: ?int, is_self: bool}
     */
    private function entryForUser(array $userRow, int $userId, int $viewerUserId, array $scoresByUser): array
    {
        $score = $scoresByUser[$userId] ?? null;

        return [
            'user_id' => $userId,
            'display_name' => UserProfile::displayName($userRow),
            'ressenti_score' => $score !== null ? RessentiNote::normalizeScore($score) : null,
            'is_self' => $userId === $viewerUserId,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function fetchBestScoresByUser(int $oeuvreId, string $mediaDomain): array
    {
        if (!CatalogSchema::usesCatalogTables($this->db)) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT h.user_id, MAX(h.note) AS ressenti_score
             FROM historique h
             INNER JOIN bibliotheque b ON b.id = h.film_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.oeuvre_id = ?
               AND o.media_domain = ?
               AND h.note IS NOT NULL
               AND h.note >= ?
               AND h.note <= ?
             GROUP BY h.user_id'
        );
        $stmt->execute([
            $oeuvreId,
            $mediaDomain,
            RessentiNote::MIN_SCORE,
            RessentiNote::MAX_SCORE,
        ]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $score = RessentiNote::normalizeScore(isset($row['ressenti_score']) ? (int) $row['ressenti_score'] : null);
            if ($userId > 0 && $score !== null) {
                $out[$userId] = $score;
            }
        }

        return $out;
    }

    /**
     * @param list<array{user_id: int, display_name: string, ressenti_score: ?int, is_self: bool}> $foyer
     */
    private function isFoyerMember(array $foyer, int $userId): bool
    {
        foreach ($foyer as $entry) {
            if ((int) ($entry['user_id'] ?? 0) === $userId) {
                return true;
            }
        }

        return false;
    }
}
